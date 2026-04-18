# Phase 7: Comments Subsystem Implementation Plan

> superpowers:subagent-driven-development. 6-step TDD cycle. Full suite 0 failed before every commit.

**Goal:** Readers can leave comments on Posts and Pages. Guest users provide name + email; auth users auto-populate. All comments land as `pending` and require admin/editor approval (v1.0 doesn't ship an auto-approve path — simpler for first cut). Approved comments render on the post/page show page, threaded via `parent_id` (flat DB, indented UI, max 3 levels visual). Honeypot + rate limit block bots; Akismet is v1.x.

**Architecture:** 4 tasks. Schema per `docs/database.md §3.10` post-Phase-2 rewrite. HMAC-SHA256 for IP anonymisation (not plain SHA256 — IPv4 is rainbow-table-reversible). body + body_html dual-storage: `body` is user's plain text input, `body_html` is server-side sanitized (escape + nl2br + URL linkify) for display.

**Working dir:** `.worktrees/comments` on `feat/comments`. Baseline: 152 passed, 0 failed.

---

## Pre-flight

- HEAD: `e4e60b6`
- Full suite: `152 passed, 0 failed`
- `spatie/laravel-honeypot` installed Phase 1 Task 17 — ready to wire.

---

## Task 47: Foundation — Comment + migration + factory + policy + morph relations

**Files:**
- Create: migration `*_create_comments_table`
- Create: `app/Enums/CommentStatus.php`
- Create: `app/Models/Comment.php`
- Modify: `app/Models/Post.php` — add `comments()` + `approvedComments()` morphMany
- Modify: `app/Models/Page.php` — same two morph relations
- Create: `database/factories/CommentFactory.php`
- Create: `app/Policies/CommentPolicy.php`
- Create: `tests/Feature/Models/CommentTest.php`
- Create: `tests/Feature/Policies/CommentPolicyTest.php`

**Migration** (per `docs/database.md §3.10` post-Phase-2):
```php
Schema::create('comments', function (Blueprint $t): void {
    $t->id();
    $t->uuid('uuid')->unique();
    $t->morphs('commentable');
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
    $t->index('parent_id');
    $t->index('user_id');
});
```

**CommentStatus enum:**
```php
enum CommentStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Spam = 'spam';
    case Trash = 'trash';

    public function label(): string { ... '待审核' / '已通过' / '垃圾' / '已删除' ... }
}
```

**Comment model:**
- `HasUuids + HasFactory`
- `$fillable`: commentable_type, commentable_id, parent_id, user_id, guest_name, guest_email, guest_ip_hash, user_agent, body, body_html, status, approved_at
- `casts()`: `status => CommentStatus::class`, `approved_at => 'datetime'`
- `commentable()` → `morphTo()`
- `parent()` → `belongsTo(self)`
- `children()` → `hasMany(self, 'parent_id')` (for nested rendering — note: application enforces 3-level limit, DB allows any depth)
- `user()` → `belongsTo(User::class)`
- `approvedChildren()` — scope sibling for convenience
- `scopePending`, `scopeApproved`, `scopeSpam`, `scopeTrash` scopes
- `isGuest()` helper returning `$this->user_id === null`
- `authorName()` helper returning `$this->user?->name ?? $this->guest_name` — works for both cases

**Post + Page morphMany additions:**

Both models gain:
```php
public function comments(): MorphMany
{
    return $this->morphMany(Comment::class, 'commentable');
}

public function approvedComments(): MorphMany
{
    return $this->morphMany(Comment::class, 'commentable')
        ->where('status', CommentStatus::Approved);
}
```

**CommentPolicy:**
- viewAny: public (approved only visible; pending filtered at query level, not policy)
- view: approved → public; others → author/editor/admin
- create: any authenticated OR guest (return `true` — gate is applied at FormRequest + throttle + honeypot level, not policy)
- update: admin/editor/admin-bypass only (users can't edit comments after submission in v1.0)
- delete: admin/editor
- approve: admin/editor (custom ability, used by CommentResource bulk actions)

**Factory** with states:
- `pending()` (default)
- `approved()` sets `status = Approved` and `approved_at = now()`
- `spam()`, `trash()`
- `guest()` sets `user_id = null`, fills guest_name / guest_email
- `byUser(User $user)` sets user_id, leaves guest fields null
- `for($post)` / `for($page)` — standard factory morph support

**Tests (~15 total, split 10 model + 5 policy):**

Model tests (`CommentTest.php`):
- Comment factory creates with status=pending
- Comment attaches polymorphically to Post
- Comment attaches polymorphically to Page
- `approvedComments` scope on Post filters by status
- Nested: parent/children relation works
- Cascade: deleting parent comment cascades children
- Guest comment has user_id=null + guest_name
- Auth comment has user_id set + guest_name null
- `authorName()` returns user's name for auth, guest_name for guest
- Scopes (pending/approved/spam) filter by status

Policy tests:
- Editor can approve; author cannot
- Admin can delete any comment
- Guest user sees approved comments
- super_admin bypasses via Gate::before

**Steps:**
- [ ] 47.1 Create all files
- [ ] 47.2 Filter: `--filter='CommentTest|CommentPolicyTest'` — 15 passes
- [ ] 47.3 pint + full suite → `167 passed, 0 failed` (152 + 15 new)
- [ ] 47.4 Commit: `feat(comments): foundation — model + migration + factory + policy`

---

## Task 48: Submission — controller + FormRequest + honeypot + HMAC + config

**Files:**
- Create: `config/forge.php` — project-level custom config (first time)
- Create: `app/Http/Controllers/Web/CommentController.php`
- Create: `app/Http/Requests/StoreCommentRequest.php`
- Create: `app/Support/CommentIpHasher.php` — tiny service for HMAC
- Modify: `routes/web.php` — add 2 POST routes (one for each polymorphic target)
- Modify: `bootstrap/app.php` — register honeypot middleware alias (if not auto-aliased)
- Modify: `.env.example` — add `COMMENT_IP_HMAC_SECRET=` placeholder
- Create: `tests/Feature/Web/CommentSubmissionTest.php`

**`config/forge.php`** (per `docs/laravel.md §10.2`):
```php
return [
    'comments' => [
        'ip_hmac_secret' => env('COMMENT_IP_HMAC_SECRET', ''),
        'require_moderation' => env('COMMENTS_REQUIRE_MODERATION', true),
        'allow_guests' => env('COMMENTS_ALLOW_GUESTS', true),
    ],
];
```

**`CommentIpHasher`:**
```php
public function hash(string $ip): string
{
    $secret = config('forge.comments.ip_hmac_secret');
    if ($secret === '' || $secret === null) {
        throw new \RuntimeException('COMMENT_IP_HMAC_SECRET must be set in .env');
    }
    return hash_hmac('sha256', $ip, $secret);
}
```

**`StoreCommentRequest`:**
```php
public function rules(): array
{
    return [
        'body' => ['required', 'string', 'min:2', 'max:5000'],
        'parent_id' => ['nullable', 'integer', 'exists:comments,id'],
        'guest_name' => ['required_without:user', 'nullable', 'string', 'max:100'],
        'guest_email' => ['required_without:user', 'nullable', 'email', 'max:255'],
    ];
}

public function authorize(): bool
{
    return true; // FormRequest gates syntax; authZ + honeypot + throttle are in middleware chain
}
```

**`CommentController::store`:**
Takes a `Post` OR `Page` (two routes). Resolves commentable, validates via StoreCommentRequest, computes body_html via `nl2br(e($body))` + auto-linkify via `Str::of($body)->markdown(...)` OR a simpler sanitizer.

Simplest body_html strategy for v1.0: `nl2br(e($body))` — escape + newlines. No auto-linking. Keep it boring.

```php
public function store(StoreCommentRequest $request, string $commentable, string $uuid): RedirectResponse
{
    // Resolve polymorphic target from URL
    $model = match ($commentable) {
        'posts' => Post::where('uuid', $uuid)->firstOrFail(),
        'pages' => Page::where('slug', $uuid)->firstOrFail(),
        default => abort(404),
    };

    // Check comments are enabled on this content
    abort_unless($model->is_comments_enabled, 403, 'Comments are disabled on this content.');

    $user = $request->user();
    $body = $request->string('body')->value();
    $comment = $model->comments()->create([
        'parent_id' => $request->integer('parent_id') ?: null,
        'user_id' => $user?->id,
        'guest_name' => $user ? null : $request->string('guest_name')->value(),
        'guest_email' => $user ? null : $request->string('guest_email')->value(),
        'guest_ip_hash' => app(CommentIpHasher::class)->hash($request->ip()),
        'user_agent' => \Str::limit($request->userAgent() ?? '', 500, ''),
        'body' => $body,
        'body_html' => nl2br(e($body)),
        'status' => config('forge.comments.require_moderation')
            ? CommentStatus::Pending
            : CommentStatus::Approved,
        'approved_at' => config('forge.comments.require_moderation') ? null : now(),
    ]);

    return back()->with('success', 'Comment submitted.');
}
```

**Routes:**
```php
use App\Http\Controllers\Web\CommentController;

Route::post('/posts/{post:uuid}/comments', fn (\App\Models\Post $post, StoreCommentRequest $r) => app(CommentController::class)->store($r, 'posts', $post->uuid))
    ->middleware(['throttle:comments', ProtectAgainstSpam::class])
    ->name('posts.comments.store');

Route::post('/pages/{page:slug}/comments', ...)->name('pages.comments.store');
```

Actually simpler — pass the generic types via the route action path instead of string switching:

```php
Route::post('/posts/{post:uuid}/comments', [CommentController::class, 'storeForPost'])
    ->middleware(['throttle:3,1', ProtectAgainstSpam::class])
    ->name('posts.comments.store');

Route::post('/pages/{page:slug}/comments', [CommentController::class, 'storeForPage'])
    ->middleware(['throttle:3,1', ProtectAgainstSpam::class])
    ->name('pages.comments.store');
```

Then `storeForPost(StoreCommentRequest, Post)` and `storeForPage(StoreCommentRequest, Page)` both delegate to a private `persistComment(...)`.

**Throttle:** `3 per minute` per IP. Laravel's built-in `throttle:3,1`.

**Honeypot middleware:** `ProtectAgainstSpam` from `spatie/laravel-honeypot` — already installed Phase 1 Task 17. Adds a hidden field the bot will fill; real users' browsers don't fill it.

**Tests (~6):**
- Guest can POST /posts/{uuid}/comments → pending created
- Auth user POST → pending (default config) or approved if `require_moderation=false`
- Honeypot field filled → rejected (422 or redirect with no comment)
- Rate limit 3/min kicks in on 4th request
- IP hash is HMAC (not raw SHA256) — check length 64 and not match `hash('sha256', $ip)` output
- Comments disabled on target → 403

**Steps:**
- [ ] 48.1 Create all files. Add `COMMENT_IP_HMAC_SECRET=...` + `COMMENTS_REQUIRE_MODERATION=true` + `COMMENTS_ALLOW_GUESTS=true` to `.env.example`. Generate a fresh 64-byte secret for `.env` locally.
- [ ] 48.2 Filter: `--filter=CommentSubmissionTest` — 6 passes
- [ ] 48.3 pint + full suite → `173 passed, 0 failed` (167 + 6 new)
- [ ] 48.4 Commit: `feat(comments): add submission endpoint with honeypot + HMAC`

---

## Task 49: Filament CommentResource — moderation UI

**Files:**
- Scaffold + customize: `app/Filament/Resources/Comments/*` (Resource + Schemas/CommentForm + Tables/CommentsTable + Pages/{ListComments,EditComment}; no CreateComment needed — admins don't create comments from scratch)
- Create: `tests/Feature/Admin/CommentResourceTest.php`

**Form:** status Select (enum), body Textarea (readable, admin can edit/clean if needed), `body_html` readonly (or hidden — we regenerate on update), optional `admin_notes` field if we want (skip for now, defer to v1.x).

**Table:**
- Columns: body (limit 100), author (computed: user.name or guest_name), commentable (morph label), status (badge), created_at
- Filters: status, commentable_type
- Row actions: approve, mark_spam, trash (+ existing edit/delete)
- Bulk actions: approve selected, mark spam, delete
- Default sort: created_at desc (newest pending first)

**Approve action:**
```php
Action::make('approve')
    ->action(fn (Comment $r) => $r->update([
        'status' => CommentStatus::Approved,
        'approved_at' => now(),
    ]))
    ->visible(fn (Comment $r) => $r->status !== CommentStatus::Approved)
    ->color('success')
```

**Tests (~4):**
- Admin accesses /admin/comments index
- Guest redirected
- Approve action flips status + sets approved_at
- CommentResource::getModel() === Comment::class

**Steps:**
- [ ] 49.1 Scaffold `make:filament-resource Comment --no-interaction` + customize
- [ ] 49.2 Write tests
- [ ] 49.3 pint + full suite → `177 passed, 0 failed`
- [ ] 49.4 Commit: `feat(comments): add Filament CommentResource for moderation`

---

## Task 50: Frontend — display + form

**Files:**
- Modify: `app/Http/Resources/PostResource.php` — include `comments` (approved tree) when loaded
- Modify: `app/Http/Resources/PageResource.php` — same
- Modify: `app/Http/Controllers/Web/PostController.php` — `show()` eager-loads `approvedComments.user` + `approvedComments.children.user`
- Modify: `app/Http/Controllers/Web/PageController.php` — same
- Create: `app/Http/Resources/CommentResource.php` — slim transform
- Create: `resources/js/types/comment.ts`
- Create: `resources/js/components/CommentThread.tsx`
- Create: `resources/js/components/CommentForm.tsx`
- Modify: `resources/js/pages/Posts/Show.tsx` — render CommentThread + CommentForm below article
- Modify: `resources/js/pages/Pages/Show.tsx` — same
- Modify: `tests/Feature/Web/PostPageTest.php` + `PageShowTest.php` — add "approved comments appear" + "pending hidden" tests (2-3 new cases per file)

**CommentThread** renders a list of top-level comments, each with nested children up to 3 levels visual.

**CommentForm** has name+email fields (hidden when user authenticated), body textarea, honeypot hidden field, posts to wayfinder-generated `postComments.store(post.uuid)` / `pageComments.store(page.slug)`.

**PostResource / PageResource change:**
```php
'comments' => $this->whenLoaded('approvedComments', fn () => CommentResource::collection($this->approvedComments)),
```

**CommentResource:**
```php
return [
    'uuid' => $this->uuid,
    'parentId' => $this->parent_id,
    'body' => $this->body_html, // sanitized HTML (nl2br + escape)
    'authorName' => $this->authorName(),
    'isGuest' => $this->isGuest(),
    'createdAt' => $this->created_at->toIso8601String(),
    'children' => CommentResource::collection($this->whenLoaded('approvedChildren')),
];
```

**Tests (~4 new):**
- Post show page returns approved comments in props
- Draft/pending comments excluded from show payload
- Comment thread nested children appear
- is_comments_enabled=false hides form in test assertion

**Steps:**
- [ ] 50.1 Backend changes + CommentResource + 2 test additions
- [ ] 50.2 Wayfinder regen
- [ ] 50.3 Frontend: types + CommentThread + CommentForm + Show.tsx integrations
- [ ] 50.4 types:check clean
- [ ] 50.5 pint + full suite → `181 passed, 0 failed`
- [ ] 50.6 Commit: `feat(comments): render comment tree + form on Post + Page show`

---

## Self-review

1. **Scope coverage:** foundation (47) + submission (48) + moderation (49) + display (50). All four CRUD vectors covered. ✓
2. **Security:** HMAC IP hash (not plain SHA256 — docs/database.md §3.10 post-Phase-2 correctness); honeypot middleware from spatie; throttle 3/min; body escape + nl2br (no XSS via comment body).
3. **Akismet** deferred to v1.x per PRD §3.2 rescope (Phase 2 Task 29).
4. **3-level nesting** is a UI render concern, not DB — schema allows any depth.

**Known risks:**
1. `config/forge.php` is the first project-level custom config. Tests need `COMMENT_IP_HMAC_SECRET` — add to `phpunit.xml` env block or use `config(['forge.comments.ip_hmac_secret' => 'test-secret'])` in test setUp.
2. Honeypot middleware class path: verify it's `Spatie\Honeypot\ProtectAgainstSpam` (likely correct). Its behavior: redirects to back() silently on honeypot-filled submissions. Tests should POST with the honeypot field NOT set (success) and with it set (rejected).
3. Filament CommentResource may scaffold a CreatePage — delete or leave unused (admins don't create comments manually). Document the decision.
4. PostForm `is_comments_enabled` field already exists (Task 37); just make sure Post show controller honors it — the check already ends up in CommentController::store's `abort_unless`.
5. Comment's `parent_id` references `comments.id` (not uuid) — cascade-delete propagates children automatically via FK constraint. DB-layer safety net.
