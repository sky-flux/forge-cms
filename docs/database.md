# ForgeCMS 数据模型

> 本文档描述 v1.0 的数据库 schema 设计、表间关系和关键索引策略。迁移文件是权威来源，本文是**设计意图和约束说明**。

## 基线假设

> 已与 PRD §7 锁定的决策对齐：
> - 后台 **Filament 5.5.2** + **spatie/laravel-permission**
> - 编辑器 **TipTap (via Filament RichEditor)**：`body_html` 主存储 + `body_markdown` 兼容字段
> - **多语言 v1.0 包含**：`posts` / `pages` 走独立 translation 表 + `locale` 字段
> - **评论 v1.0 包含**：`comments` polymorphic 关联，管理员审核流

---

## 1. 数据库总览

- **DBMS**：PostgreSQL 18.3
- **字符编码**：UTF-8
- **时区**：`UTC`（应用层处理时区转换）
- **主键策略**：`bigint` 自增 + **`uuid` 外显**字段（前台 URL 用 uuid 或 slug，避免暴露自增 id 让人猜总数）
- **软删除**：内容类表（`posts`/`pages`/`media`）启用 `deleted_at`；用户类不软删（改用状态字段）
- **时间戳**：所有表带 `created_at` / `updated_at`

---

## 2. ER 图（文字版）

```
users ──┬── (1:N) ── posts ────── (1:N) ── post_translations
        ├── (1:N) ── pages ────── (1:N) ── page_translations
        ├── (1:N) ── media
        └── (1:N) ── comments

categories ──(self:parent_id)── categories
         └── (1:N) ── category_translations
         └── (N:M via post_category) ── posts

tags ──── (1:N) ── tag_translations
     └── (N:M via post_tag) ──── posts

comments ──(polymorphic)── posts / pages
        └──(self:parent_id)── comments (nested reply)

roles / permissions (spatie 包生成) ──── users

settings (单表，配置 kv 存储)
```

---

## 3. 表详细设计

### 3.1 `users`

| 字段 | 类型 | 约束 / 默认 | 说明 |
|------|------|-------------|------|
| id | `bigint` | PK, auto | |
| uuid | `uuid` | UNIQUE, default `gen_random_uuid()` | 外显使用 |
| name | `varchar(100)` | NOT NULL | |
| email | `varchar(255)` | UNIQUE, NOT NULL | |
| email_verified_at | `timestamptz` | NULL | |
| password | `varchar(255)` | NOT NULL | Laravel bcrypt |
| avatar_path | `varchar(500)` | NULL | 存储桶相对路径 |
| bio | `text` | NULL | 作者简介，前台页面展示 |
| status | `varchar(20)` | default `active` | `active` / `suspended` |
| last_login_at | `timestamptz` | NULL | |
| remember_token | `varchar(100)` | NULL | |
| created_at / updated_at | `timestamptz` | | |

**索引**：`email` UNIQUE；`uuid` UNIQUE；`status` BTREE。

### 3.2 `roles` + `role_user`

使用 [spatie/laravel-permission](https://spatie.be/docs/laravel-permission/v6/introduction) 的表结构，初始 seed 三个角色：`admin` / `editor` / `author`。

```sql
roles: id, name, guard_name, created_at, updated_at
role_user: role_id, model_type, model_id, (PK: 三者联合)
```

> **说明**：Filament 5 与 spatie/laravel-permission 集成成熟，直接用包的迁移即可，不自建 schema。

### 3.3 `posts`（不含翻译字段）

多语言拆分:**`posts` 存结构元数据**(作者、状态、分类关联、发布时间),**`post_translations` 存每个语言的标题/正文**。

| 字段 | 类型 | 约束 / 默认 | 说明 |
|------|------|-------------|------|
| id | `bigint` | PK | |
| uuid | `uuid` | UNIQUE | |
| user_id | `bigint` | FK → users.id, ON DELETE RESTRICT | 作者 |
| status | `varchar(20)` | default `draft` | `draft` / `published` / `scheduled` |
| published_at | `timestamptz` | NULL | 发布时间 |
| featured_image_id | `bigint` | FK → media.id, ON DELETE SET NULL | 封面图 |
| view_count | `integer` | default 0 | 异步从 Valkey 同步 |
| is_comments_enabled | `boolean` | default true | 单篇级别评论开关 |
| meta | `jsonb` | default `'{}'` | 跨语言共享的元数据(og:image、自定义字段) |
| deleted_at | `timestamptz` | NULL | |
| created_at / updated_at | `timestamptz` | | |

**索引**:`status` + `published_at` 复合;`user_id`;`uuid`;`deleted_at` 部分索引。

### 3.3.1 `post_translations`

| 字段 | 类型 | 约束 / 默认 | 说明 |
|------|------|-------------|------|
| id | `bigint` | PK | |
| post_id | `bigint` | FK → posts.id, CASCADE | |
| locale | `varchar(10)` | NOT NULL | `zh_CN` / `en` / ... |
| title | `varchar(255)` | NOT NULL | |
| slug | `varchar(255)` | NOT NULL | URL 片段,按语言独立 |
| excerpt | `varchar(500)` | NULL | |
| body_html | `text` | NOT NULL | TipTap 产出的 HTML |
| body_markdown | `text` | NULL | 可选 MD 导出格式 |
| seo_title | `varchar(255)` | NULL | 覆盖默认 SEO title |
| seo_description | `varchar(500)` | NULL | |
| created_at / updated_at | | | |

**索引**:
- `(post_id, locale)` UNIQUE —— 一篇文章一个语言只能有一个翻译
- `(locale, slug)` UNIQUE —— 同语言内 slug 不重复,跨语言可重
- `(locale, post_id)` BTREE —— 按语言查询时走索引

**迁移草例**:

```php
Schema::create('posts', function (Blueprint $t) {
    $t->id();
    $t->uuid('uuid')->unique();
    $t->foreignId('user_id')->constrained()->restrictOnDelete();
    $t->string('status', 20)->default('draft');
    $t->timestampTz('published_at')->nullable();
    $t->foreignId('featured_image_id')->nullable()->constrained('media')->nullOnDelete();
    $t->integer('view_count')->default(0);
    $t->boolean('is_comments_enabled')->default(true);
    $t->jsonb('meta')->default('{}');
    $t->softDeletes();
    $t->timestampsTz();
    $t->index(['status', 'published_at']);
});

Schema::create('post_translations', function (Blueprint $t) {
    $t->id();
    $t->foreignId('post_id')->constrained()->cascadeOnDelete();
    $t->string('locale', 10);
    $t->string('title');
    $t->string('slug');
    $t->string('excerpt', 500)->nullable();
    $t->text('body_html');
    $t->text('body_markdown')->nullable();
    $t->string('seo_title')->nullable();
    $t->string('seo_description', 500)->nullable();
    $t->timestampsTz();
    $t->unique(['post_id', 'locale']);
    $t->unique(['locale', 'slug']);
});
```

### 3.4 `pages` + `page_translations`

与 `posts` 同构(不关联分类/标签),翻译同样独立表:

`pages` 字段:`id`, `uuid`, `status`, `published_at`, `sort_order`, `is_homepage`, `meta`, `deleted_at`, timestamps。

`page_translations` 字段:`id`, `page_id`, `locale`, `title`, `slug`, `body_html`, `body_markdown`, `seo_title`, `seo_description`, timestamps。索引同 `post_translations`。

### 3.5 `categories` + `category_translations`

`categories`(结构):

| 字段 | 类型 | 说明 |
|------|------|------|
| id | bigint | PK |
| parent_id | bigint | FK self, NULL for root |
| sort_order | integer | 同级排序 |
| timestamps | | |

`category_translations`(本地化):

| 字段 | 类型 | 说明 |
|------|------|------|
| id | bigint | PK |
| category_id | bigint | FK → categories.id, CASCADE |
| locale | varchar(10) | |
| name | varchar(100) | |
| slug | varchar(100) | |
| description | text | NULL |

**索引**:`(category_id, locale)` UNIQUE;`(locale, slug)` UNIQUE。

**约束**:三级嵌套上限通过应用层校验;避免循环引用(parent_id 不能直接/间接指回自己)。

### 3.6 `post_category` (pivot)

```
post_id (FK, cascade delete)
category_id (FK, cascade delete)
PK (post_id, category_id)
```

### 3.7 `tags` + `tag_translations`

`tags`(结构):`id`, `timestamps`

`tag_translations`(本地化):

| 字段 | 类型 | 说明 |
|------|------|------|
| id | bigint | PK |
| tag_id | bigint | FK, CASCADE |
| locale | varchar(10) | |
| name | varchar(50) | citext 大小写不敏感 |
| slug | varchar(50) | |

**索引**:`(tag_id, locale)` UNIQUE;`(locale, slug)` UNIQUE;`(locale, name)` UNIQUE。

### 3.8 `post_tag` (pivot)

同 `post_category` 结构。

### 3.9 `media` —— 由 `spatie/laravel-medialibrary` 提供

**⚠️ 重要**:不自建 `media` 表,使用 `spatie/laravel-medialibrary` 包提供的 schema,其表结构由包的迁移文件生成,字段大致:

| 字段 | 类型 | 说明 |
|------|------|------|
| id | bigint | PK |
| uuid | uuid | UNIQUE |
| model_type | varchar | polymorphic,媒体挂在哪个模型上(Post/Page/User) |
| model_id | bigint | polymorphic ID |
| collection_name | varchar | 'featured_images' / 'gallery' / 'attachments' |
| name | varchar | 原始文件名(无扩展名) |
| file_name | varchar | 保存后的文件名 |
| mime_type | varchar | |
| disk | varchar | `local` / `s3` / `r2` —— 从 `.env` 读取 |
| conversions_disk | varchar | 缩略图存储盘(可不同于原文件) |
| size | bigint | 字节 |
| manipulations | json | 图像变换参数 |
| custom_properties | json | alt_text、caption、EXIF 等 |
| generated_conversions | json | `{"thumb": true, "preview": true}` |
| responsive_images | json | srcset 用的多尺寸信息 |
| order_column | integer | 同集合内排序 |
| created_at / updated_at | | |

**为什么用包而不是自建**:
- 图片自动多尺寸转换(缩略图、响应式图片 srcset)
- 多 disk 无缝切换(本地/S3/R2 改 `.env` 即可)
- Filament 有官方 plugin `filament/spatie-laravel-media-library-plugin`,后台上传组件零配置
- 图片变换(裁剪、水印)用 `spatie/image`(同作者)链式 API
- 每年被 Laravel 官方 Stats 评为前 10 最受欢迎包,长期维护

**应用层关联**(Post/Page 等模型):

```php
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Post extends Model implements HasMedia
{
    use InteractsWithMedia;

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('featured')->singleFile();
        $this->addMediaCollection('gallery');
    }

    public function registerMediaConversions(Media $media = null): void
    {
        $this->addMediaConversion('thumb')->width(400)->format('webp');
        $this->addMediaConversion('preview')->width(1200)->format('webp');
    }
}
```

这替代了原先 `posts.featured_image_id` 字段 —— 改用 `$post->getFirstMediaUrl('featured', 'thumb')` 取值。

迁移上 `posts` 表**删除** `featured_image_id` 字段(之前示例里的),取而代之由 media_library 的 polymorphic 关联负责。

### 3.10 `comments`

Polymorphic 关联(同一张表服务 posts 和 pages),支持嵌套回复。

| 字段 | 类型 | 约束 / 默认 | 说明 |
|------|------|-------------|------|
| id | `bigint` | PK | |
| uuid | `uuid` | UNIQUE | 外显使用 |
| commentable_type | `varchar(100)` | NOT NULL | `App\Models\Post` / `App\Models\Page` |
| commentable_id | `bigint` | NOT NULL | 目标 ID |
| parent_id | `bigint` | FK self, NULL | 回复的父评论 |
| user_id | `bigint` | FK → users.id, SET NULL | 注册用户评论时填;游客留 NULL |
| guest_name | `varchar(100)` | NULL | 游客昵称 |
| guest_email | `varchar(255)` | NULL | 游客邮箱(不公开) |
| guest_ip_hash | `varchar(64)` | NULL | IP 的 SHA256(GDPR 考虑,不存明文) |
| user_agent | `varchar(500)` | NULL | 反垃圾审计 |
| body | `text` | NOT NULL | 纯文本评论内容 |
| body_html | `text` | NOT NULL | 渲染后的 HTML(净化过的) |
| status | `varchar(20)` | default `pending` | `pending` / `approved` / `spam` / `trash` |
| approved_at | `timestamptz` | NULL | 审核通过时间 |
| created_at / updated_at | `timestamptz` | | |

**索引**:
- `(commentable_type, commentable_id, status)` 复合 —— 查"某篇文章的已通过评论"最高频
- `status` BTREE —— 管理后台"待审核"列表
- `parent_id` BTREE —— 构建评论树
- `user_id` BTREE

**迁移草例**:

```php
Schema::create('comments', function (Blueprint $t) {
    $t->id();
    $t->uuid('uuid')->unique();
    $t->morphs('commentable');  // commentable_type + commentable_id + index
    $t->foreignId('parent_id')->nullable()->constrained('comments')->cascadeOnDelete();
    $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
    $t->string('guest_name', 100)->nullable();
    $t->string('guest_email')->nullable();
    $t->string('guest_ip_hash', 64)->nullable();
    $t->string('user_agent', 500)->nullable();
    $t->text('body');
    $t->text('body_html');
    $t->string('status', 20)->default('pending');
    $t->timestampTz('approved_at')->nullable();
    $t->timestampsTz();
    $t->index(['commentable_type', 'commentable_id', 'status']);
    $t->index('status');
});
```

**设计说明**:
- **IP 存哈希不存明文**:GDPR 合规,仅用于同 IP 频率限制和屏蔽,不可逆
- **游客 vs 注册用户**:`user_id` NULL 时用 `guest_name`/`guest_email`;两者互斥由应用层保证
- **评论不软删**:审核中的垃圾评论直接 `status=spam` 或 `status=trash`,真正清理由定时任务做
- **反垃圾**:v1.0 用 honeypot 字段 + 速率限制;v1.x 可选集成 Akismet

### 3.11 `settings`

键值表，存全站配置（站点名、主题、SMTP、SEO 默认值等）。

| 字段 | 类型 | 说明 |
|------|------|------|
| key | varchar(100) | PK |
| value | jsonb | 支持标量、数组、对象 |
| description | text | 管理后台展示用 |
| updated_at | timestamptz | |

**为什么 jsonb 而不是 text**：避免反复 serialize/deserialize；Postgres 原生支持 JSON 查询。

### 3.12 Laravel 标配表

以下由框架和官方包生成，直接复用迁移文件，不重复设计：

- `password_reset_tokens`
- `sessions`（若用 DB driver；我们用 redis driver，可跳过）
- `cache`（若用 DB driver；我们用 redis）
- `jobs`, `failed_jobs`（Laravel Horizon 主要用 Redis，但失败任务仍落 DB）
- `personal_access_tokens`（Sanctum，v1.x API 时启用）

---

## 4. 关系与约束总结

| 关系 | 类型 | 删除级联策略 |
|------|------|-------------|
| `posts.user_id → users.id` | N:1 | **RESTRICT**（作者有文章不能删） |
| `posts.featured_image_id → media.id` | N:1 | **SET NULL** |
| `post_category` | N:M | CASCADE 两侧 |
| `post_tag` | N:M | CASCADE 两侧 |
| `categories.parent_id → categories.id` | 自关联 | **RESTRICT**（有子分类不能删父） |
| `media.user_id → users.id` | N:1 | RESTRICT |

---

## 5. 索引策略

**高频查询**：
1. 前台首页："已发布 + 按 published_at 倒序 + 分页" → `(status, published_at)` 复合索引
2. 文章详情页：按 slug → `slug` UNIQUE 索引
3. 分类归档："某分类下所有已发布" → pivot 表 + `status` 索引
4. 搜索：**不走数据库**，走 Meilisearch，定期 sync

**避免过度索引**：每个索引都有写放大成本。不给 `title` / `body_markdown` 建普通索引 —— 全文检索交给 Meilisearch。

---

## 6. 数据量估算（第 1 年预期）

| 表 | 预期行数 | 增速假设 |
|----|---------|---------|
| `users` | 10 - 100 | 单站团队规模 |
| `posts` | 1k - 10k | 每天 5-10 篇 |
| `post_translations` | 2k - 20k | ≈ `posts` × 语言数(默认 2) |
| `pages` | 10 - 50 | 基本不增长 |
| `page_translations` | 20 - 100 | 同上 |
| `media` | 10k - 100k | 每篇文章平均 5-10 张图 |
| `categories` | 10 - 100 | 基本不增长 |
| `category_translations` | 20 - 200 | |
| `tags` | 100 - 5k | 随内容线性 |
| `tag_translations` | 200 - 10k | |
| `post_tag` | 3k - 50k | 每篇 3-5 个标签 |
| `comments` | 10k - 500k | 每篇 10-50 条(含垃圾),热门站点更多 |

Postgres 18 处理这个量级毫无压力,甚至索引都未必需要复杂策略。**单机 Postgres 足够到 v2.0**。评论可能是增长最快的表,如果做反垃圾不力,冷数据占比会非常高 —— 必要时定期归档 `status=spam` 的老数据到独立表。

---

## 7. Seed 与默认数据

首次 migrate 后,`db:seed` 生成:

- 1 个 admin 用户（邮箱从 `.env` 读取，密码随机生成并打印到控制台）
- 3 个角色: `admin`, `editor`, `author`（权限矩阵见 `RolePermissionSeeder`）
- 1 个示例 Page（"关于我们"），标记为 homepage=true
- 1 个示例 Post（"欢迎使用 ForgeCMS"）
- 3 个默认分类: "未分类", "技术", "生活"
- `settings` 表基础键: `site_name`, `site_description`, `default_theme`, `posts_per_page`

---

## 8. 未来扩展预留

以下 v1.x 再加,v1.0 表结构已做兼容:

- **API Tokens**:启用 Sanctum 的 `personal_access_tokens` 表
- **版本历史**:新建 `post_revisions` 表保存每次保存的快照(`post_id`, `locale`, `title`, `body_html`, `user_id`, `created_at`)
- **自定义内容类型**:`posts` 表加 `type` 字段(default `post`),或独立 `content_types` 表 + EAV 模式(后者代价高,慎用)
- **评论增强**:接入 Akismet(当前表的 `body_html` / `user_agent` / `guest_ip_hash` 已准备好送检字段)
- **全文检索同步**:Meilisearch 索引在模型 `saved` / `deleted` 事件触发 sync;目前 `posts` + `post_translations` 联表作为单一文档 index,`locale` 作为 filter 字段
