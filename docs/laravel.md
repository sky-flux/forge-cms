# ForgeCMS Laravel 开发规范

> 本文档定义 forge-cms 项目的 Laravel 代码约定、设计模式和禁区。**一切和这里矛盾的外部教程都按本文档为准**。目标受众:进入本仓库写代码的开发者。

## 读前必知

- Laravel **13.x** + PHP **8.5.5**,一切利用 PHP 8.5 新特性(readonly、enum、named args、property hooks)
- **Octane worker 模式生产运行**(FrankenPHP),因此代码必须 **Octane-safe**
- 前台 **Inertia + React**,Controller 返回 `Inertia::render`,不返回 Blade 视图
- 后台 **Filament 5.5.2**,独占 `/admin/*`,走自己的 Livewire 生命周期

---

## 1. 最高优先级:Octane-safe 原则

Octane worker 模式下**容器进程常驻**,每个请求共享进程内存。以下模式会串数据或内存泄漏:

### 1.1 禁止:静态属性持有请求级状态

```php
// ❌ 错误 —— 第二个请求还能看到第一个请求的数据
class PostService
{
    private static ?User $currentUser = null;

    public function setUser(User $user): void
    {
        self::$currentUser = $user;
    }
}
```

```php
// ✅ 正确 —— 从 auth() 或 container 获取
class PostService
{
    public function __construct(
        private readonly AuthManager $auth,
    ) {}

    public function getCurrentUser(): ?User
    {
        return $this->auth->user();
    }
}
```

### 1.2 禁止:singleton 绑定中持有请求数据

```php
// ❌ 错误 —— AppServiceProvider 里
$this->app->singleton(Reporter::class, function ($app) {
    return new Reporter(request()->user()); // 第一个请求的 user 永久挂住
});

// ✅ 正确 —— scoped 绑定(Laravel 9+ 新语法,Octane 友好)
$this->app->scoped(Reporter::class, function ($app) {
    return new Reporter($app['auth']->user());
});
```

**`scoped` 绑定**:每个请求新建一次,请求结束自动清理。是 Octane 时代的默认选择。

### 1.3 禁止:config 运行时修改

```php
// ❌ 永远不要在 Controller/Middleware 里改 config
config(['app.timezone' => $user->timezone]);  // 下一个请求仍然是这个时区
```

### 1.4 Static cache 只放真正不变的数据

```php
// ✅ OK:常量级别的静态缓存
class Countries
{
    private static ?array $iso = null;

    public static function all(): array
    {
        return self::$iso ??= require __DIR__.'/iso-3166.php';
    }
}

// ❌ 不 OK:包含用户/请求维度的缓存
class PostCache
{
    private static array $perUser = [];  // 会无限膨胀
}
```

**规则**:static 变量只允许存**与请求/用户无关**的数据。用户维度缓存走 `Cache::remember` 配合 TTL。

---

## 2. 目录与命名约定

### 2.1 现代 Laravel 11+/13 结构

Laravel 11 起官方简化了骨架(**不再有**以下文件):

- ~~`app/Http/Kernel.php`~~ → 中间件注册在 `bootstrap/app.php`
- ~~`app/Console/Kernel.php`~~ → 调度任务写在 `routes/console.php`
- ~~`App\Providers\RouteServiceProvider`~~ → 路由在 `bootstrap/app.php` 的 `withRouting()`
- ~~`App\Exceptions\Handler`~~ → 异常处理在 `bootstrap/app.php` 的 `withExceptions()`

**别照老教程抄**,进新骨架。

### 2.2 业务代码组织

```
app/
├── Actions/                    # 单动作类(可选,见 §3.3)
│   └── Posts/PublishPost.php
├── Enums/                      # 8.1+ enum 类型
│   ├── PostStatus.php
│   └── UserRole.php
├── Filament/                   # 后台
│   ├── Resources/
│   ├── Pages/
│   └── Widgets/
├── Http/
│   ├── Controllers/
│   │   ├── Web/                # 前台(返回 Inertia::render)
│   │   └── Api/                # v1.x API 路由控制器
│   ├── Middleware/
│   ├── Requests/               # FormRequest 统一校验
│   └── Resources/              # API Resource(API 返回 transform)
├── Jobs/
├── Models/
│   └── Concerns/               # Trait
├── Notifications/
├── Observers/                  # 模型事件处理(spatie/activitylog 用)
├── Policies/                   # 授权策略
├── Providers/
├── Rules/                      # 自定义验证规则
├── Services/                   # 领域服务(见 §3.2)
└── Support/                    # 工具类
```

### 2.3 命名

- **Controller**:`PostController`,方法遵循 RESTful(`index/show/store/update/destroy`)
- **Model**:单数,`Post`,不写 `Posts`
- **FormRequest**:`StorePostRequest` / `UpdatePostRequest`(动作+资源)
- **Policy**:`PostPolicy`,方法 `viewAny/view/create/update/delete/restore/forceDelete`
- **Resource**:`PostResource`(单个),`PostCollection`(集合,仅当需要自定义 meta)
- **Job**:动词开头,`SendCommentNotification`
- **Event**:过去时,`PostPublished`
- **Listener**:动词原形,`NotifySubscribersOfNewPost`
- **Action**:动词开头,`PublishPost`

---

## 3. 分层架构

### 3.1 Controller:尽量薄

Controller 的唯一职责:**接收请求 → 调用业务逻辑 → 返回响应**。不写业务逻辑。

```php
// ✅ 好
class PostController extends Controller
{
    public function store(StorePostRequest $request, CreatePost $action): RedirectResponse
    {
        $post = $action->execute($request->validated(), $request->user());

        return redirect()
            ->route('posts.show', $post)
            ->with('success', __('post.created'));
    }

    public function show(Post $post): Response
    {
        $this->authorize('view', $post);

        return Inertia::render('Posts/Show', [
            'post' => new PostResource($post->load('tags', 'translations')),
            'comments' => CommentResource::collection($post->approvedComments),
        ]);
    }
}
```

```php
// ❌ 差 —— 业务逻辑塞 Controller
public function store(Request $request)
{
    $data = $request->validate([...]);
    $slug = Str::slug($data['title']);
    if (Post::where('slug', $slug)->exists()) { $slug .= '-'.Str::random(4); }
    $html = (new CommonMarkConverter())->convert($data['body'])->getContent();
    $post = Post::create([...+slug+html+user]);
    foreach ($data['tags'] as $tag) { $post->tags()->attach(Tag::firstOrCreate(['name' => $tag])); }
    Meilisearch::index('posts')->addDocuments([$post->toSearchArray()]);
    event(new PostPublished($post));
    return redirect(...);
}
```

### 3.2 Service vs Action

**Service**:跨多个动作的**领域对象**,有状态管理或复杂组合。

**Action**:单一职责的**一次性操作**,无状态,输入 → 输出。

```php
// Action:单动作,简单
class PublishPost
{
    public function __construct(
        private readonly CommonMarkConverter $markdown,
        private readonly SearchIndexer $search,
        private readonly Dispatcher $events,
    ) {}

    public function execute(Post $post): Post
    {
        $post->update([
            'status' => PostStatus::Published,
            'published_at' => now(),
        ]);

        $this->search->index($post);
        $this->events->dispatch(new PostPublished($post));

        return $post->fresh();
    }
}

// Service:领域对象,跨动作
class CommentModerationService
{
    public function approve(Comment $comment): void { ... }
    public function markSpam(Comment $comment): void { ... }
    public function isLikelySpam(Comment $comment): bool { ... }
}
```

**经验法则**:能写成 Action 的不写 Service。Service 容易变成"万能神类"。

### 3.3 Repository 模式:**不要用**

Laravel 的 Eloquent 已经是 repository 的实现。再套一层 `PostRepository` 是把简单问题复杂化,增加维护成本。

```php
// ❌ 没必要
class PostRepository
{
    public function findPublished(int $id): ?Post
    {
        return Post::where('status', 'published')->find($id);
    }
}

// ✅ 直接用 Eloquent scope
Post::published()->find($id);

// Model 里:
public function scopePublished(Builder $q): void
{
    $q->where('status', PostStatus::Published);
}
```

唯一的例外:切换底层 ORM(几乎不可能发生) 或 跨多个数据源。

---

## 4. Eloquent Model 约定

### 4.1 Model 骨架

```php
namespace App\Models;

use App\Enums\PostStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Scout\Searchable;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Post extends Model implements HasMedia
{
    use HasFactory, HasUuids, InteractsWithMedia, Searchable, SoftDeletes;

    protected $fillable = [
        'user_id', 'status', 'published_at', 'is_comments_enabled', 'meta',
    ];

    protected function casts(): array
    {
        return [
            'status' => PostStatus::class,
            'published_at' => 'datetime',
            'is_comments_enabled' => 'boolean',
            'meta' => 'array',
        ];
    }

    // ---- 关联 ----
    public function user() { return $this->belongsTo(User::class); }
    public function translations() { return $this->hasMany(PostTranslation::class); }
    public function tags() { return $this->belongsToMany(Tag::class); }
    public function comments() { return $this->morphMany(Comment::class, 'commentable'); }

    // ---- Scope ----
    public function scopePublished(Builder $q): void
    {
        $q->where('status', PostStatus::Published)
          ->where('published_at', '<=', now());
    }

    public function scopeInLocale(Builder $q, string $locale): void
    {
        $q->whereHas('translations', fn ($q) => $q->where('locale', $locale));
    }

    // ---- Route model binding 定制 ----
    public function getRouteKeyName(): string { return 'uuid'; }
}
```

### 4.2 一定要 `casts()` 方法,不用 `$casts` 属性

Laravel 11+ 推荐 casts 方法形式(类型推断更好,IDE 友好)。

### 4.3 Enum cast 优先于字符串常量

```php
// ❌ 旧写法
class Post {
    const STATUS_DRAFT = 'draft';
    const STATUS_PUBLISHED = 'published';
}

// ✅ 新写法
enum PostStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Scheduled = 'scheduled';

    public function label(): string
    {
        return match($this) {
            self::Draft => __('status.draft'),
            self::Published => __('status.published'),
            self::Scheduled => __('status.scheduled'),
        };
    }
}
```

### 4.4 N+1 预防

所有 Inertia props 必须预加载关联:

```php
// ❌ 列表页 N+1
return Inertia::render('Posts/Index', [
    'posts' => Post::published()->get(),  // 遍历时 lazy load tags/translations
]);

// ✅ 显式 with()
return Inertia::render('Posts/Index', [
    'posts' => Post::published()
        ->with(['translations' => fn ($q) => $q->where('locale', app()->getLocale())])
        ->with('tags.translations')
        ->paginate(12),
]);
```

开发环境开启 `Model::preventLazyLoading()`(在 `AppServiceProvider::boot()`),遇到 N+1 直接抛异常,强制发现问题。

---

## 5. 验证与授权

### 5.1 FormRequest 统一校验

Controller 方法签名只认 FormRequest,不要直接用 `$request->validate()`:

```php
class StorePostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Post::class);
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'body_html' => ['required', 'string'],
            'status' => ['required', Rule::enum(PostStatus::class)],
            'tags' => ['array', 'max:10'],
            'tags.*' => ['string', 'max:50'],
            'locale' => ['required', Rule::in(config('app.available_locales'))],
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => __('validation.post.title_required'),
        ];
    }
}
```

### 5.2 Policy 统一授权

```php
class PostPolicy
{
    public function viewAny(?User $user): bool { return true; }  // 公开列表

    public function view(?User $user, Post $post): bool
    {
        return $post->status === PostStatus::Published
            || $user?->can('view drafts')
            || $user?->id === $post->user_id;
    }

    public function create(User $user): bool
    {
        return $user->can('create posts');
    }

    public function update(User $user, Post $post): bool
    {
        return $user->can('update any posts')
            || ($user->can('update own posts') && $user->id === $post->user_id);
    }
}
```

Controller 里**必须**调用 `$this->authorize()`,不要自己写 if 判断。

### 5.3 权限键来自 spatie + filament-shield

不要手写权限字符串。运行 `php artisan shield:generate` 让 Shield 扫描 Filament Resources 生成权限,然后用 `$user->can('update_post')`。

---

## 6. Inertia 模式

### 6.1 Controller 返回 `Inertia::render`

```php
return Inertia::render('Posts/Show', [
    'post' => new PostResource($post),
    'related' => PostResource::collection($related),
    'locale' => app()->getLocale(),
]);
```

Page 名("Posts/Show")映射到 `resources/js/Pages/Posts/Show.tsx`。

### 6.2 Share 全局数据用 `HandleInertiaRequests` middleware

```php
// app/Http/Middleware/HandleInertiaRequests.php
public function share(Request $request): array
{
    return [
        ...parent::share($request),
        'auth' => [
            'user' => $request->user()?->only('id', 'name', 'email', 'role'),
        ],
        'flash' => [
            'success' => fn () => $request->session()->get('success'),
            'error' => fn () => $request->session()->get('error'),
        ],
        'locale' => fn () => app()->getLocale(),
        'availableLocales' => fn () => config('app.available_locales'),
    ];
}
```

闭包形式(`fn () =>`)懒加载,只在实际需要时计算。

### 6.3 lazy props(`Inertia::lazy()`)

不要一次性返回所有数据。分页数据、大列表用 lazy:

```php
return Inertia::render('Posts/Index', [
    'posts' => Inertia::lazy(fn () => Post::published()->paginate(12)),
    'filters' => $request->only(['search', 'tag']),
]);
```

客户端按需触发 `router.reload({ only: ['posts'] })` 加载。

### 6.4 SSR props 必须是可序列化的

不要把 Eloquent Model 直接丢进 props(含未加载的关联、闭包等,SSR 序列化会挂)。**永远经过 Resource 转换**:

```php
class PostResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'title' => $this->translationFor($request->locale)?->title,
            'slug' => $this->translationFor($request->locale)?->slug,
            'bodyHtml' => $this->when($this->resource->relationLoaded('translations'), ...),
            'publishedAt' => $this->published_at?->toIso8601String(),
            'tags' => TagResource::collection($this->whenLoaded('tags')),
        ];
    }
}
```

---

## 7. 队列与事件

### 7.1 Queue driver = redis,走 Horizon

```php
class SendCommentNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 10;  // 重试间隔秒

    public function __construct(public readonly Comment $comment) {}

    public function handle(Mailer $mailer): void
    {
        $mailer->to(...)->send(new CommentReceived($this->comment));
    }
}
```

### 7.2 事件驱动写副作用

```php
// 主流程只发事件
PostPublished::dispatch($post);

// Listener 处理副作用(异步)
class IndexPostInSearch implements ShouldQueue
{
    public function handle(PostPublished $event): void
    {
        $event->post->searchable();
    }
}
```

**原则**:主流程(Controller/Action)保持同步,副作用(搜索索引、邮件、webhook)走事件 + queue listener。主流程挂了不影响副作用补偿,反之亦然。

---

## 8. 测试(Pest)

### 8.1 目录

```
tests/
├── Feature/             # HTTP 层、端到端
│   ├── Web/
│   │   ├── PostShowTest.php
│   │   └── CommentCreateTest.php
│   └── Admin/
│       └── PostResourceTest.php
├── Unit/                # 无框架依赖
│   └── Services/
└── Pest.php             # 全局配置
```

### 8.2 Pest 风格

```php
use function Pest\Laravel\{actingAs, get, post};

it('shows published posts to guests', function () {
    $post = Post::factory()->published()->create();

    get(route('posts.show', $post))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Posts/Show')
            ->where('post.id', $post->uuid)
        );
});

it('hides draft posts from guests', function () {
    $post = Post::factory()->draft()->create();

    get(route('posts.show', $post))->assertNotFound();
});

it('allows author to preview own draft', function () {
    $author = User::factory()->create();
    $post = Post::factory()->draft()->for($author)->create();

    actingAs($author)
        ->get(route('posts.show', $post))
        ->assertOk();
});
```

### 8.3 Factory 里 state

```php
class PostFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'status' => PostStatus::Draft,
            'is_comments_enabled' => true,
        ];
    }

    public function published(): static
    {
        return $this->state(fn () => [
            'status' => PostStatus::Published,
            'published_at' => now()->subHour(),
        ]);
    }

    public function draft(): static
    {
        return $this->state(fn () => ['status' => PostStatus::Draft]);
    }
}
```

### 8.4 必跑的三条命令

```bash
./vendor/bin/pint --test     # 代码风格检查(不改)
./vendor/bin/phpstan analyse # 静态分析
./vendor/bin/pest            # 测试
```

CI 上三个都跑。本地提交前 `./vendor/bin/pint && ./vendor/bin/pest` 过一遍。

---

## 9. 日志与错误处理

### 9.1 结构化日志

生产环境 Caddyfile.prod 已配 `format json`,Laravel 端也统一 JSON:

```php
// config/logging.php
'default' => env('LOG_CHANNEL', 'stderr'),

'channels' => [
    'stderr' => [
        'driver' => 'monolog',
        'level' => env('LOG_LEVEL', 'info'),
        'handler' => StreamHandler::class,
        'formatter' => JsonFormatter::class,
        'with' => ['stream' => 'php://stderr'],
    ],
],
```

输出到 stderr,容器日志由 Docker/Compose 统一收集。

### 9.2 日志方法

```php
Log::info('Post published', [
    'post_id' => $post->id,
    'user_id' => $user->id,
    'duration_ms' => $timer->elapsed(),
]);
```

**不要**把完整 Model 对象 dump 进日志(序列化太大,可能泄露敏感字段)。只记 ID + 关键字段。

### 9.3 异常处理

```php
// bootstrap/app.php
->withExceptions(function (Exceptions $exceptions) {
    $exceptions->render(function (NotFoundHttpException $e, Request $request) {
        if ($request->header('X-Inertia')) {
            return Inertia::render('Errors/NotFound', status: 404);
        }
    });

    $exceptions->reportable(function (Throwable $e) {
        // 上报到 Sentry(未来接入)
    });
})
```

---

## 10. 配置与环境变量

### 10.1 永远走 `config()`,不直接读 `env()`

```php
// ❌ 在 Controller/Service 里
$key = env('MEILISEARCH_KEY');  // config cache 后失效!

// ✅
$key = config('scout.meilisearch.key');
```

**只有 `config/*.php` 文件**能调 `env()`,因为生产 `config:cache` 后 `.env` 不再被读取。

### 10.2 新增配置项

```php
// config/forge.php(项目自定义)
return [
    'available_locales' => ['zh_CN', 'en'],
    'default_locale' => 'zh_CN',
    'comments' => [
        'require_moderation' => env('COMMENTS_REQUIRE_MODERATION', true),
        'allow_guests' => env('COMMENTS_ALLOW_GUESTS', true),
    ],
];
```

---

## 11. 常见反模式 —— 禁做清单

- ❌ Controller 里直接用 `DB::table()` —— 走 Eloquent
- ❌ `dd()` / `dump()` 进生产 —— CI 里 grep 拦截
- ❌ `->get()` 之后 PHP 侧 filter —— 在数据库里完成
- ❌ `Auth::user()` 全局调用 —— 注入 `AuthManager` 或用 `$request->user()`
- ❌ 直接在 Blade / React 里跑 query —— 永远由 Controller 查好再传
- ❌ `@csrf` 写在非 POST 表单 —— Inertia 自动处理
- ❌ 写自己的分页逻辑 —— 用 `paginate()` / `cursorPaginate()`
- ❌ 自己写 SQL 字符串拼接 —— 用 Query Builder
- ❌ 把敏感字段(password / token)塞 Resource —— Resource 层必须过滤
- ❌ 跨时区比较 datetime —— 统一 UTC 存,展示时转

---

## 12. 升级与维护

### 12.1 依赖升级节奏

- **Laravel**:跟 LTS + 1 次 minor,不追 master
- **PHP**:跟当前 stable(8.5),不提前切到 RC
- **Composer 包**:每月跑 `composer outdated`,major 升级走 PR review
- **npm/bun 包**:Renovate 自动提 PR,patch 自动合,minor 人工,major 讨论

### 12.2 Rector 辅助重构

```bash
./vendor/bin/rector process --dry-run   # 预览
./vendor/bin/rector process             # 实际应用
```

Rector 规则集:`rector-laravel` + `rector/rector`(PHP 语言级)。每次 Laravel 大版本升级后跑一次,自动适配 deprecated API。

---

## 13. 完整 composer 依赖清单

> 所有版本号**实测于 2026-04-18**,Packagist API 直查。全部 PHP 8.5.5 兼容(composer 的 `^8.x` 约定展开是 `>=x.y <9.0`,天然涵盖 8.5)。

### 13.1 生产依赖(`require`)

**状态列说明**:`✅ starter` = `laravel new --react` 骨架默认装好的;`✅ installed` = bootstrap 后通过 `composer require` 补装。版本号从 `composer.lock` 取实测值。

| 包 | 当前稳定版 | 状态 | PHP 约束 | 用途 |
|----|-----------|------|----------|------|
| `laravel/framework` | v13.5.0 | ✅ starter | `^8.3` | 框架本体 |
| `laravel/tinker` | v3.0 | ✅ starter | `^8.2` | artisan tinker REPL |
| `inertiajs/inertia-laravel` | v3.0.6 | ✅ starter | `^8.2.0` | Inertia 服务端 adapter |
| `laravel/fortify` | v1.34 | ✅ starter | `^8.2` | 认证后端(login/register/2FA/密码重置),纯后端无 UI |
| `laravel/wayfinder` | v0.1.14 | ✅ starter | `^8.2` | **取代 Ziggy**:Laravel 路由自动生成类型安全的 TS 函数 |
| `laravel/octane` | v2.17.1 | ✅ installed | `^8.1.0` | FrankenPHP worker runner |
| `laravel/horizon` | v5.45.6 | ✅ installed | `^8.0` | Redis 队列 dashboard |
| `laravel/sanctum` | v4.3.1 | ✅ installed | `^8.2` | API tokens(v1.x 开放 REST API 时用) |
| `laravel/scout` | v11.1.0 | ✅ installed | `^8.0` | 搜索抽象层 |
| `laravel/reverb` | v1.10.0 | ✅ installed | `^8.2` | 官方 WebSocket broadcaster |
| `meilisearch/meilisearch-php` | v1.16.1 | ✅ installed | `^7.4 \|\| ^8.0` | Scout 的 Meili 驱动 |
| `filament/filament` | v5.5.2 | ✅ installed | `^8.2` | 管理后台核心 |
| `bezhansalleh/filament-shield` | 4.2.0 | ✅ installed | `^8.2` | Filament + spatie/permission 桥接 |
| `filament/spatie-laravel-media-library-plugin` | v5.5.2 | ✅ installed | `^8.2` | Filament media 上传组件 |
| `spatie/laravel-permission` | 7.3.0 | ✅ installed | `^8.3` | 角色与权限 |
| `spatie/laravel-medialibrary` | 11.21.0 | ✅ installed | `^8.2` | 媒体文件管理 |
| `spatie/laravel-sluggable` | 3.8.1 | ✅ installed | `^8.2` | 自动 slug 生成 |
| `spatie/laravel-sitemap` | 8.1.0 | ✅ installed | `^8.4` | `sitemap.xml` 生成 |
| `spatie/laravel-activitylog` | 5.0.0 | ✅ installed | `^8.4` | 审计日志 |
| `spatie/laravel-backup` | 10.2.1 | ✅ installed | `^8.3` | 定时备份 |
| `spatie/laravel-honeypot` | 4.7.1 | ✅ installed | `^8.2` | 评论/表单反垃圾 |
| `spatie/laravel-feed` | 4.5.0 | ✅ installed | `^8.2` | RSS/Atom feed(v1.x 用) |

> **关键变更**:`tightenco/ziggy` **不再推荐** —— Laravel 2026 推出的官方 `laravel/wayfinder` 取代了它,`--react` starter 默认装。Wayfinder 生成类型安全 TS 函数,比 Ziggy 的字符串 `route()` 调用更能防错。

### 13.2 开发依赖(`require-dev`)

**状态列说明**:`✅ starter` = `laravel new --react --pest` 骨架默认装好的;`✅ installed` = bootstrap 后通过 `composer require --dev` 补装。

| 包 | 当前稳定版 | 状态 | PHP 约束 | 用途 |
|----|-----------|------|----------|------|
| `laravel/pint` | v1.29.0 | ✅ starter | `^8.2.0` | 代码格式化(官方) |
| `laravel/pail` | v1.2.6 | ✅ starter | `^8.2` | 实时日志 tail |
| `laravel/boost` | v2.4 | ✅ starter | `^8.2` | Laravel Boost MCP 服务端 |
| `laravel/mcp` | v0.6.7 | ✅ starter | `^8.2` | MCP 基础 |
| `pestphp/pest` | v4.6.3 | ✅ starter | `^8.3.0` | 测试框架 |
| `pestphp/pest-plugin-laravel` | v4.1 | ✅ starter | `^8.2` | Pest 的 Laravel 扩展 |
| `fakerphp/faker` | v1.24 | ✅ starter | `^8.2` | 测试假数据 |
| `mockery/mockery` | v1.6 | ✅ starter | `^8.2` | Mock 对象 |
| `nunomaduro/collision` | v8.9 | ✅ starter | `^8.2` | 漂亮的异常输出 |
| `barryvdh/laravel-ide-helper` | v3.7.0 | ✅ installed | `^8.2` | IDE 提示文件生成 |
| `laravel/telescope` | v5.20.0 | ✅ installed | `^8.0` | 请求/查询调试面板 |
| `larastan/larastan` | v3.9.6 | ✅ installed | `^8.2` | PHPStan for Laravel |
| `rector/rector` | 2.4.2 | ✅ installed | `^7.4 \|\| ^8.0` | 自动重构 |
| `driftingly/rector-laravel` | 2.3.0 | ✅ installed | `^7.4 \|\| ^8.0` | Rector 的 Laravel 规则集 |

> **laravel/sail 清理状态**:已于 commit `2a88f54` 移除 —— Colima + 我们的 compose 文件已覆盖 Sail 的所有功能。详见 §13.5 / PRD §9.5。

### 13.3 可直接粘贴的 `composer.json` require 块

> 这是本项目 `composer.json` 的**实际状态**(截至最后一次 `feat(deps)` / `chore(deps)` commit)。任何未来想基于同款技术栈起新项目的人,复制这两块 `require` / `require-dev` 即可复刻 forge-cms 的后端依赖矩阵。

```json
{
    "require": {
        "php": "^8.3",
        "bezhansalleh/filament-shield": "^4.2",
        "filament/filament": "^5.5",
        "filament/spatie-laravel-media-library-plugin": "^5.5",
        "inertiajs/inertia-laravel": "^3.0",
        "laravel/fortify": "^1.34",
        "laravel/framework": "^13.0",
        "laravel/horizon": "^5.45",
        "laravel/octane": "^2.17",
        "laravel/reverb": "^1.10",
        "laravel/sanctum": "^4.0",
        "laravel/scout": "^11.1",
        "laravel/tinker": "^3.0",
        "laravel/wayfinder": "^0.1.14",
        "meilisearch/meilisearch-php": "^1.16",
        "spatie/laravel-activitylog": "^5.0",
        "spatie/laravel-backup": "^10.2",
        "spatie/laravel-feed": "^4.5",
        "spatie/laravel-honeypot": "^4.7",
        "spatie/laravel-medialibrary": "^11.21",
        "spatie/laravel-permission": "^7.3",
        "spatie/laravel-sitemap": "^8.1",
        "spatie/laravel-sluggable": "^3.8"
    },
    "require-dev": {
        "barryvdh/laravel-ide-helper": "^3.7",
        "driftingly/rector-laravel": "^2.3",
        "fakerphp/faker": "^1.24",
        "larastan/larastan": "^3.9",
        "laravel/boost": "^2.4",
        "laravel/mcp": "^0.6.7",
        "laravel/pail": "^1.2.5",
        "laravel/pint": "^1.27",
        "laravel/telescope": "^5.20",
        "mockery/mockery": "^1.6",
        "nunomaduro/collision": "^8.9",
        "pestphp/pest": "^4.6",
        "pestphp/pest-plugin-laravel": "^4.1",
        "rector/rector": "^2.4"
    }
}
```

**安装顺序**:真实的 package 落地顺序记录在 git 历史里 —— 每个 package 都对应一个独立 `feat(deps)` commit(附带冒烟测试)。查询方法:

```bash
git log --grep='^feat(deps)' --reverse --oneline
```

这比本文档维护一份会漂移的清单更可靠。下面的 bootstrap 骨架命令对**新 clone** 仍然正确:

```bash
# ========== 宿主机(Herd Lite)==========

# 1. Laravel 骨架到临时目录再合并到根(避开已有 docs/、compose 文件冲突)
cd ~/workspace/forge-cms
laravel new tmp --react --pest --bun --database=pgsql --no-interaction
# --react 把 Inertia + React + shadcn/ui + Vite + Tailwind + 身份验证 UI 一并装好
# --pest 用 Pest 替代 PHPUnit;--bun 用 Bun 做包管理;--database 预填 .env
rm -rf tmp/.git 2>/dev/null
rsync -a --ignore-existing tmp/ . && rm -rf tmp

# 2. 宿主机跑的 artisan(不连服务)
php artisan key:generate               # 写 APP_KEY 到 .env

# ========== 启动容器服务 ==========

make dev                               # 启动 postgres / valkey / meilisearch / frankenphp

# ========== 容器内 artisan(需要服务)==========

# 3. 各大包的迁移 + 初始化 —— 具体每个包做了什么,看对应的 feat(deps) commit
make migrate
make ca octane:install --server=frankenphp
make ca horizon:install
make ca telescope:install
make ca filament:install --panels
make ca shield:install && make ca shield:generate

# ========== 前端 ==========

bun install
bun run build
```

**流程核心**:
- **依赖解析(host composer)** + **应用运行(container)** + **服务(container)** 三者分离
- `vendor/` 由 host 生成,通过 `compose.override.yml` 的 bind mount 让容器读取
- `composer.lock` 进 Git,生产构建时容器用它做 `composer install --no-dev`

### 13.4 关于 Packagist 头部流行包

Packagist "popular" 页面前 30 全是**传递依赖**:`symfony/polyfill-*`、`psr/*`、`symfony/console`、`monolog/monolog`、`guzzlehttp/*`。这些都已随 Laravel/Symfony/Guzzle **自动 install**,**不要**也**不用**在你的 composer.json 里显式 require —— 违反"显式依赖声明"原则,升级冲突时排查麻烦。

真正值得明示声明的是 §13.1 / §13.2 这些"应用层业务包"。

### 13.5 明确**不**用的包 + 原因

| ❌ 包 | 原因 | 替代 |
|------|------|------|
| `laravel/sail` | Colima + 我们的 compose 已覆盖,重复 —— 已于 commit `2a88f54` 移除 | Colima + `compose.yml` / `compose.override.yml` |
| `tightenco/ziggy` | 被 `laravel/wayfinder` 取代(类型安全更强) | `laravel/wayfinder`(`--react` 默认装) |
| `barryvdh/laravel-debugbar` | Telescope 更现代、功能覆盖、和 Octane 兼容 | `laravel/telescope` |
| `predis/predis` | 比 phpredis 扩展慢 10x+ | Dockerfile 已装 `ext-redis` |
| `beyondcode/laravel-comments` | 维护不活跃 | 自建 `comments` 表(database.md §3.10) |
| `spatie/laravel-comments` | 付费包(Spatie Enterprise) | 自建 |
| `spatie/laravel-translatable` | 把翻译塞主表 JSON,不利于索引/搜索 | 独立 `*_translations` 表(database.md §3.3.1) |
| `spatie/laravel-tags` | 多态 + 翻译表已满足需求 | 自建 `tags` + `tag_translations` |
| `jenssegers/agent` | Laravel 11+ 已内建 `request()->userAgent()` | 原生 |
| `fruitcake/laravel-cors` | Laravel 11+ 原生有 cors 配置 | `config/cors.php` |
| `laravel/breeze` / `laravel/jetstream` | 已用 `--react` starter + Fortify | — |
| `laravel-mix` | Vite 已取代 | `vite` + `laravel-vite-plugin` |

---

## 14. Laravel 13 部署与工具要点

基于官方 [Laravel Deployment](https://laravel.com/docs/13.x/deployment) / [Configuration](https://laravel.com/docs/13.x/configuration) / [FrankenPHP](https://frankenphp.dev/docs/laravel/) 综合。

### 14.1 部署缓存用 `optimize`,不用 4 条分离命令

**❌ 老写法**:

```bash
php artisan config:cache
php artisan event:cache
php artisan route:cache
php artisan view:cache
```

**✅ Laravel 13 推荐**:

```bash
php artisan optimize        # 等价上面四条 + composer dump-autoload 优化
php artisan optimize:clear  # 清除所有缓存
```

`deploy/Dockerfile` prod stage 和 `Makefile prod-deploy` 目标改用 `optimize`。

### 14.2 部署后用 `reload` 重启 worker

Laravel 13 新增的`php artisan reload` —— 一条命令重启所有长驻进程(Octane / Horizon / Reverb / queue worker)。

```bash
# 生产部署尾声
php artisan migrate --force
php artisan optimize
php artisan reload                # 让所有 worker 读到新代码
```

以前要写 `supervisorctl restart` 或对 Octane 发 SIGUSR2,现在一条 artisan 命令覆盖全部。

### 14.3 `/up` 健康检查端点

Laravel 11+/13 内置健康路由,**默认在 `bootstrap/app.php` 里已启用**:

```php
->withRouting(
    web: __DIR__.'/../routes/web.php',
    commands: __DIR__.'/../routes/console.php',
    health: '/up',               // ← 这里
)
```

`GET /up` 返回 200(应用启动正常)或 500(启动失败)。

**自定义健康诊断**(例如检查数据库、Redis):

```php
// app/Providers/AppServiceProvider.php
use Illuminate\Foundation\Events\DiagnosingHealth;

public function boot(): void
{
    Event::listen(function (DiagnosingHealth $event) {
        DB::connection()->getPdo();
        Cache::store('redis')->get('health-check');
    });
}
```

**Docker healthcheck 集成**:在 `compose.prod.yml` 的 `app` 服务加:

```yaml
healthcheck:
  test: ["CMD", "curl", "-fsS", "http://localhost/up"]
  interval: 30s
  timeout: 5s
  retries: 3
  start_period: 30s   # 给 Octane worker 预热时间
```

### 14.4 Laravel Boost(可选)—— AI 协作 MCP 服务

[Laravel Boost](https://laravel.com/docs/13.x/ai) 是 Laravel 官方的 MCP(Model Context Protocol)服务器,让 Claude Code / Cursor 等 AI 工具**真正理解你的代码库**:

- 查询路由、模型、配置
- 直接执行 artisan 命令
- Tinker 里跑代码验证假设
- 按版本搜索 Laravel 官方文档

**安装**:

```bash
composer require laravel/boost --dev
php artisan boost:install
```

启用后,你在 Claude Code 里问"这个项目有哪些路由"时,AI 可以调用 Boost MCP 直接查 `route:list`,而不是靠静态代码分析猜。

**何时启用**:
- ✅ 团队重度用 AI 辅助编码(Claude Code / Cursor 等)
- ✅ 新人 onboarding(AI 可以帮答"这个 Resource 怎么改")
- ❌ 不用 AI 工具的纯人工团队,加它只增加 composer 体积

### 14.5 `env:encrypt` 加密 `.env`(可选,生产 secrets 管理)

```bash
# 加密(密钥输出到控制台,妥善保存)
php artisan env:encrypt

# 用自定义密钥
php artisan env:encrypt --key=base64:YOUR_32_BYTE_KEY

# 保留变量名可读(便于 PR review)
php artisan env:encrypt --readable

# 解密(部署时用)
php artisan env:decrypt --key=base64:YOUR_32_BYTE_KEY
```

产出 `.env.encrypted`,可以**安全提交到 Git**。部署时设 `LARAVEL_ENV_ENCRYPTION_KEY=...` 环境变量,Laravel 启动时自动解密。

**取舍**:
- ✅ 替代 Vault / Doppler / 1Password CLI 的轻量级方案
- ✅ 和 Git 流程无缝
- ❌ 密钥轮换麻烦(所有历史 commit 的 `.env.encrypted` 用旧 key 加密)
- ❌ 多环境(dev/staging/prod)要多把密钥

我们 v1.0 **不启用**,直接在服务器上维护 `.env`(不提交) 就够。v1.x 上 CI/CD 后再考虑。

### 14.6 Octane 在 FrankenPHP 下的运行时选项

```bash
php artisan octane:frankenphp \
    --host=0.0.0.0 --port=80 \
    --workers=auto              # 进程数,留 auto 让 FrankenPHP 按 CPU 决定
    --max-requests=500          # 处理 500 个请求后重启该 worker(避免内存泄漏)
    --watch                     # dev 才用:代码改动自动重载 worker
    --log-level=info
```

**生产不要加 `--watch`**(会监听文件变动,每次改动重启 worker,性能损失大)。
**开发也一般不用 `--watch`**,因为我们开发环境根本不跑 Octane,用标准 `frankenphp run` + `php_server`。

### 14.7 `php artisan about` —— 配置审计

快速查看应用的所有关键配置:

```bash
php artisan about
# 输出:
#   Environment, Laravel version, PHP version
#   Cache / Config / Routes / Events / Views 缓存状态
#   Debug mode, Maintenance mode
#   Database / Queue / Mail / Session drivers
#   ...
```

部署后排查"生产跑起来不对劲"时第一条要跑的命令。

---

## 附录:快速参考

### 常用 Artisan

```bash
php artisan make:model Post -mfsc           # model + migration + factory + seeder + controller
php artisan make:request StorePostRequest
php artisan make:policy PostPolicy --model=Post
php artisan make:filament-resource Post --generate
php artisan shield:generate                 # 刷新权限
php artisan scout:import "App\Models\Post" # 重建 Meilisearch 索引
php artisan octane:reload                   # 生产代码改完后重载 worker
```

### 有用的依赖注入

```php
public function __construct(
    private readonly AuthManager $auth,
    private readonly Dispatcher $events,
    private readonly Factory $validator,
    private readonly Translator $translator,
    private readonly UrlGenerator $url,
) {}
```

优先注入契约(`Illuminate\Contracts\...`),不注入具体实现。
