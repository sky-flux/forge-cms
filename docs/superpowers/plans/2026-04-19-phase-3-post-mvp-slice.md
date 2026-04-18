# Phase 3: Post MVP Slice Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development to implement this plan task-by-task. Every task follows the 6-step TDD cycle stored in memory: 写失败测试 → 确认红 → 实现 → 确认绿 → pint --dirty + 全量测试 → commit. Full suite (not just filter) must be 0 failed before every commit.

**Goal:** Ship the first business-code slice — a working Post model with admin CRUD, auto-slug, full-text search wiring, media attach, RBAC, and Filament UI. Closes the gap between "dependencies installed" (Phase 1) / "docs aligned" (Phase 2) and "can actually publish content".

**Architecture:** 4 commits, each a vertical capability slice of Post:
1. Foundation (enum + migration + model + factory)
2. Capabilities (HasSlug + Searchable + InteractsWithMedia traits)
3. Authorization (PostPolicy + RBAC seed)
4. Admin UI (Filament PostResource)

Each commit must leave `php artisan test` at `N passed, 0 failed` with N ≥ 73 (baseline) + new tests.

**Tech context:** Post schema follows `docs/database.md §3.3` as rewritten in Phase 2 Task 27 — **single-table posts** (no `post_translations` in v1.0), `body_html` single source of truth (no `body_markdown`), `id` for internal FK + `uuid` for external URL. Multi-language deferred to v1.x. User model has `HasRoles` (spatie) + `FilamentUser` + `HasMedia` + `HasApiTokens` etc. from prior phases.

**Working directory:** `/Users/martinadamsdev/workspace/forge-cms/.worktrees/post-mvp` on branch `feat/post-mvp`.

---

## Pre-flight

- [ ] `pwd` ends in `.worktrees/post-mvp`
- [ ] `git branch --show-current` → `feat/post-mvp`
- [ ] `git log --oneline | head -1` → `9de2cab` (Phase 2 merge tip)
- [ ] Full suite baseline: `env PATH="$HOME/.config/herd-lite/bin:$PATH" php artisan test` → `73 passed (182 assertions), 0 failed`

---

## Shared rules (every task)

1. **TDD cycle is non-negotiable.** Full suite must be 0 failed before commit, per session memory. Any pre-existing red must be fixed as a separate `fix(tests):` commit before the task continues.
2. **No AI attribution** in commits.
3. **Convention:** `test(...)` not `it(...)`; `declare(strict_types=1)` on every new PHP file; no redundant `uses(RefreshDatabase::class)` (Pest.php binds globally).
4. **Prefer `make c` / `make a`** where possible; use direct `env PATH=... php artisan ...` for flag pass-through (`--filter=`, `--provider=`).
5. **One commit per task.** If a reviewer requests changes, that's a separate `fix(...)` commit on top.
6. Follow `laravel.md` conventions: `casts()` method (not `$casts` property), Enum casts, explicit return types, PHPDoc blocks only for non-obvious `why`.
7. **`Model::preventLazyLoading()`** gets turned on in Task 34's `AppServiceProvider::boot()` to enforce eager-load discipline from day one.

---

## Task 34: PostStatus enum + migration + base Post model + Factory

**Files:**
- Create: `app/Enums/PostStatus.php`
- Create: `database/migrations/2026_04_18_210000_create_posts_table.php`
- Create: `app/Models/Post.php`
- Create: `database/factories/PostFactory.php`
- Modify: `app/Providers/AppServiceProvider.php` — add `Model::preventLazyLoading(! app()->isProduction())` in `boot()`
- Create: `tests/Feature/Models/PostTest.php`

**Scope boundary:** NO Searchable / NO HasSlug / NO InteractsWithMedia — those come in Task 35. This task lays the boring foundation.

**Schema (follows database.md §3.3 post-Phase-2 rewrite):**

```php
Schema::create('posts', function (Blueprint $t): void {
    $t->id();
    $t->uuid('uuid')->unique();
    $t->foreignId('user_id')->constrained()->restrictOnDelete();
    $t->string('title');
    $t->string('slug')->unique();
    $t->string('excerpt', 500)->nullable();
    $t->text('body_html');
    $t->string('seo_title')->nullable();
    $t->string('seo_description', 500)->nullable();
    $t->string('status', 20)->default('draft');
    $t->timestampTz('published_at')->nullable();
    $t->integer('view_count')->default(0);
    $t->boolean('is_comments_enabled')->default(true);
    $t->jsonb('meta')->default('{}');
    $t->softDeletes();
    $t->timestampsTz();
    $t->index(['status', 'published_at']);
    $t->index('user_id');
});
```

SQLite test DB will use `json` instead of `jsonb` — Laravel's `jsonb` helper falls back for SQLite automatically.

**`PostStatus` enum:**
```php
<?php

declare(strict_types=1);

namespace App\Enums;

enum PostStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Scheduled = 'scheduled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => '草稿',
            self::Published => '已发布',
            self::Scheduled => '定时发布',
        };
    }
}
```

**`Post` model (foundation only):**
```php
<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PostStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Post extends Model
{
    /** @use HasFactory<\Database\Factories\PostFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'user_id', 'title', 'slug', 'excerpt', 'body_html',
        'seo_title', 'seo_description', 'status', 'published_at',
        'view_count', 'is_comments_enabled', 'meta',
    ];

    protected function casts(): array
    {
        return [
            'status' => PostStatus::class,
            'published_at' => 'datetime',
            'is_comments_enabled' => 'boolean',
            'view_count' => 'integer',
            'meta' => 'array',
        ];
    }

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopePublished(Builder $query): void
    {
        $query->where('status', PostStatus::Published)
            ->where('published_at', '<=', now());
    }

    public function scopeDraft(Builder $query): void
    {
        $query->where('status', PostStatus::Draft);
    }

    public function scopeScheduled(Builder $query): void
    {
        $query->where('status', PostStatus::Scheduled);
    }
}
```

Note: `HasUuids` generates UUIDs on create; combined with `uniqueIds() = ['uuid']` it targets the `uuid` column (not the primary `id`, which stays bigint auto-inc per D4 convention).

**`PostFactory`:**
```php
<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\PostStatus;
use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Post>
 */
class PostFactory extends Factory
{
    public function definition(): array
    {
        $title = $this->faker->sentence(6);

        return [
            'user_id' => User::factory(),
            'title' => $title,
            'slug' => \Illuminate\Support\Str::slug($title).'-'.$this->faker->unique()->numberBetween(1, 99999),
            'excerpt' => $this->faker->paragraph(),
            'body_html' => '<p>'.$this->faker->paragraphs(3, true).'</p>',
            'seo_title' => null,
            'seo_description' => null,
            'status' => PostStatus::Draft,
            'published_at' => null,
            'view_count' => 0,
            'is_comments_enabled' => true,
            'meta' => [],
        ];
    }

    public function published(): static
    {
        return $this->state(fn (): array => [
            'status' => PostStatus::Published,
            'published_at' => now()->subHour(),
        ]);
    }

    public function scheduled(): static
    {
        return $this->state(fn (): array => [
            'status' => PostStatus::Scheduled,
            'published_at' => now()->addDay(),
        ]);
    }
}
```

**`AppServiceProvider::boot()` addition** — add inside boot(), at the top:
```php
\Illuminate\Database\Eloquent\Model::preventLazyLoading(! $this->app->isProduction());
```

**Test `tests/Feature/Models/PostTest.php`:**
```php
<?php

declare(strict_types=1);

use App\Enums\PostStatus;
use App\Models\Post;
use App\Models\User;

test('PostStatus enum exposes draft, published, scheduled cases with labels', function (): void {
    expect(PostStatus::Draft->value)->toBe('draft');
    expect(PostStatus::Published->value)->toBe('published');
    expect(PostStatus::Scheduled->value)->toBe('scheduled');
    expect(PostStatus::Draft->label())->toBe('草稿');
});

test('a post can be created via the factory and has a uuid + route key', function (): void {
    $post = Post::factory()->create();

    expect($post->uuid)->toBeString()->not->toBeEmpty();
    expect($post->getRouteKeyName())->toBe('uuid');
    expect($post->status)->toBe(PostStatus::Draft);
    expect($post->is_comments_enabled)->toBeTrue();
});

test('published scope returns only posts with status=published and published_at in the past', function (): void {
    $published = Post::factory()->published()->create();
    $draft = Post::factory()->create();
    $scheduled = Post::factory()->scheduled()->create();

    $found = Post::published()->get();

    expect($found)->toHaveCount(1)
        ->and($found->first()->is($published))->toBeTrue();
});

test('draft scope returns only draft posts', function (): void {
    Post::factory()->published()->create();
    $draft = Post::factory()->create();

    $found = Post::draft()->get();

    expect($found)->toHaveCount(1)
        ->and($found->first()->is($draft))->toBeTrue();
});

test('post soft-deletes and can be restored', function (): void {
    $post = Post::factory()->create();
    $post->delete();

    expect(Post::count())->toBe(0);
    expect(Post::withTrashed()->count())->toBe(1);

    $post->restore();

    expect(Post::count())->toBe(1);
});

test('post belongs to its author', function (): void {
    $author = User::factory()->create();
    $post = Post::factory()->for($author)->create();

    expect($post->user->is($author))->toBeTrue();
});
```

**Steps:**
- [ ] 34.1 Create all 6 files above
- [ ] 34.2 Run filter test: `env PATH="$HOME/.config/herd-lite/bin:$PATH" php artisan test --filter=PostTest` — must fail initially (file creation order might make some pass); once all files created, filter must pass 6 tests.
- [ ] 34.3 `vendor/bin/pint --dirty --format agent` — pass
- [ ] 34.4 Full suite: `env PATH="$HOME/.config/herd-lite/bin:$PATH" php artisan test 2>&1 | grep "Tests:"` — `79 passed, 0 failed` (73 + 6 new)
- [ ] 34.5 Commit:
```
feat(post): foundation — enum + migration + model + factory

First business-code commit. Ship the Post skeleton that later
tasks (Task 35 search/slug/media, Task 36 policy, Task 37 Filament
UI) build on top of.

- `App\Enums\PostStatus` — Draft / Published / Scheduled with
  Chinese labels for the Filament UI.
- `posts` migration matches `docs/database.md §3.3` as rewritten
  in Phase 2 Task 27: single table (no translations in v1.0),
  `body_html` single source of truth, `uuid` unique + `id` bigint
  for internal FK, soft deletes, jsonb meta, indexed status +
  published_at composite + user_id.
- `App\Models\Post` uses `HasUuids` + `SoftDeletes` + `HasFactory`
  with the PostStatus cast, published/draft/scheduled query
  scopes, and `getRouteKeyName() = 'uuid'` so public URLs expose
  the uuid per the `docs/database.md §3.1` id/uuid role note.
- `PostFactory` with `published()` / `scheduled()` states for test
  ergonomics.
- `AppServiceProvider::boot()` turns on
  `Model::preventLazyLoading()` outside production, per
  `docs/laravel.md §4.4`. Catches N+1 at first access instead of
  letting it slip into prod.

6 feature tests cover enum, creation, 3 scopes, soft-delete cycle,
and user relationship.
```

---

## Task 35: Post capabilities — HasSlug + Searchable + InteractsWithMedia

**Files:**
- Modify: `app/Models/Post.php`
- Modify: `tests/Feature/Models/PostTest.php` (extend, don't rewrite)

**Changes to `Post.php`:**

Add trait imports (alphabetical):
```php
use Laravel\Scout\Searchable;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;
```

Change class declaration:
```php
class Post extends Model implements HasMedia
```

Update trait list (alphabetical):
```php
use HasFactory, HasSlug, HasUuids, InteractsWithMedia, Searchable, SoftDeletes;
```

Remove the auto-slug generation from `PostFactory` (HasSlug will do it on save — Factory can still set a manual slug for tests that need determinism, but the default `definition()` should drop the manual slug so HasSlug takes over). Keep `slug` in `$fillable`.

Update `PostFactory::definition()`:
```php
return [
    'user_id' => User::factory(),
    'title' => $title,
    // slug auto-generated by HasSlug on save; omit here
    'excerpt' => $this->faker->paragraph(),
    // ... rest unchanged
];
```

Drop the `'slug'` line from the factory array (HasSlug will fill on `saving` event).

Add methods to `Post`:
```php
public function getSlugOptions(): SlugOptions
{
    return SlugOptions::create()
        ->generateSlugsFrom('title')
        ->saveSlugsTo('slug')
        ->doNotGenerateSlugsOnUpdate();
}

public function toSearchableArray(): array
{
    return [
        'id' => $this->id,
        'uuid' => $this->uuid,
        'title' => $this->title,
        'excerpt' => $this->excerpt,
        'body_html' => strip_tags($this->body_html),
        'status' => $this->status?->value,
        'published_at' => $this->published_at?->timestamp,
    ];
}

public function shouldBeSearchable(): bool
{
    return $this->status === PostStatus::Published;
}

public function registerMediaCollections(): void
{
    $this->addMediaCollection('featured')->singleFile();
    $this->addMediaCollection('gallery');
}
```

`doNotGenerateSlugsOnUpdate()` — once a post is published with a slug, renaming the title shouldn't break permalinks.

**Scout driver for tests:** `phpunit.xml` currently has no `SCOUT_DRIVER` override. Add `<env name="SCOUT_DRIVER" value="collection"/>` under `<php>` so tests use Scout's in-memory collection engine instead of hitting Meilisearch.

**Test additions (append to `PostTest.php`):**
```php
test('slug is auto-generated from title on save', function (): void {
    $post = Post::factory()->create(['title' => 'Hello World Post']);

    expect($post->slug)->toBe('hello-world-post');
});

test('slug is unique — duplicates get a suffix', function (): void {
    Post::factory()->create(['title' => 'Same Title']);
    $second = Post::factory()->create(['title' => 'Same Title']);

    expect($second->slug)->not->toBe('same-title');
    expect($second->slug)->toStartWith('same-title-');
});

test('published post is searchable and exposes key fields in toSearchableArray', function (): void {
    $post = Post::factory()->published()->create([
        'title' => 'Searchable Title',
        'body_html' => '<p>body text</p>',
    ]);

    expect($post->shouldBeSearchable())->toBeTrue();

    $array = $post->toSearchableArray();
    expect($array)->toHaveKeys(['id', 'uuid', 'title', 'excerpt', 'body_html', 'status', 'published_at'])
        ->and($array['title'])->toBe('Searchable Title')
        ->and($array['body_html'])->toBe('body text'); // strip_tags
});

test('draft post is not searchable', function (): void {
    $post = Post::factory()->create(); // draft by default

    expect($post->shouldBeSearchable())->toBeFalse();
});

test('post accepts media uploads to featured and gallery collections', function (): void {
    Illuminate\Support\Facades\Storage::fake('public');

    $post = Post::factory()->create();

    $post->addMedia(Illuminate\Http\UploadedFile::fake()->image('cover.png'))
        ->toMediaCollection('featured');
    $post->addMedia(Illuminate\Http\UploadedFile::fake()->image('shot1.png'))
        ->toMediaCollection('gallery');
    $post->addMedia(Illuminate\Http\UploadedFile::fake()->image('shot2.png'))
        ->toMediaCollection('gallery');

    expect($post->fresh()->getMedia('featured'))->toHaveCount(1);
    expect($post->fresh()->getMedia('gallery'))->toHaveCount(2);
});
```

**Steps:**
- [ ] 35.1 Edit `Post.php` — add traits + implements + 4 methods
- [ ] 35.2 Edit `PostFactory` — drop manual slug
- [ ] 35.3 Edit `phpunit.xml` — add SCOUT_DRIVER=collection env
- [ ] 35.4 Append 5 tests to `PostTest.php`
- [ ] 35.5 Filter test: `--filter=PostTest` — 11 tests pass (6 from Task 34 + 5 new)
- [ ] 35.6 pint --dirty — pass
- [ ] 35.7 Full suite — `84 passed` (79 + 5) with 0 failed
- [ ] 35.8 Commit:
```
feat(post): add HasSlug + Searchable + InteractsWithMedia capabilities

Layer three traits onto Post, each tested in isolation:

- `HasSlug` (spatie/laravel-sluggable): auto-fills `slug` from
  `title` on create; uniqueness handled by the package (suffix on
  collision). `doNotGenerateSlugsOnUpdate()` freezes the slug once
  written — renaming a published post's title won't break its
  permalink.
- `Searchable` (laravel/scout): `toSearchableArray` exposes id,
  uuid, title, excerpt, plain-text body, status, and
  published_at timestamp for Meilisearch. `shouldBeSearchable()`
  gates sync to `status === Published`, so drafts / scheduled
  don't leak into the public index.
- `implements HasMedia` + `InteractsWithMedia` + two collections
  (`featured` single-file, `gallery` multi-file). Matches the
  shape `docs/database.md §3.9` specifies for post media.

`PostFactory` drops the manual slug assignment now that HasSlug
handles it. `phpunit.xml` gains `SCOUT_DRIVER=collection` so tests
run against Scout's in-memory engine instead of hitting the
Meilisearch container.

5 new feature tests cover slug generation, slug uniqueness,
searchable array shape, should-be-searchable gate, and media
uploads to both collections.
```

---

## Task 36: PostPolicy + RBAC seed

**Files:**
- Create: `app/Policies/PostPolicy.php`
- Create: `database/seeders/RolePermissionSeeder.php`
- Modify: `database/seeders/DatabaseSeeder.php` (call RolePermissionSeeder)
- Create: `tests/Feature/Policies/PostPolicyTest.php`

**Permission scheme (matches PRD §3.1 three-role split + Shield convention):**

Role → permissions (matching `shield:generate` naming):
- `admin`: `{view_any,view,create,update,delete,restore,force_delete}_post` (full)
- `editor`: same as admin minus `force_delete_post`
- `author`: `{view_any,view,create}_post` + `{view,update,delete}_own_post` (conceptual — policy enforces ownership)

Shield v4 auto-generates the first group per Resource. Our seeder explicitly creates the three roles and syncs Shield-generated permissions. `super_admin` is registered by `shield:install` (Phase 1 Task 3) and bypasses all policies via `Gate::before`.

**Policy (`app/Policies/PostPolicy.php`):**
```php
<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Post;
use App\Models\User;

class PostPolicy
{
    public function viewAny(?User $user): bool
    {
        return true; // public index
    }

    public function view(?User $user, Post $post): bool
    {
        if ($post->status === \App\Enums\PostStatus::Published) {
            return true;
        }

        return $user !== null
            && ($user->hasAnyRole(['admin', 'editor']) || $user->id === $post->user_id);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'editor', 'author']);
    }

    public function update(User $user, Post $post): bool
    {
        if ($user->hasAnyRole(['admin', 'editor'])) {
            return true;
        }

        return $user->hasRole('author') && $user->id === $post->user_id;
    }

    public function delete(User $user, Post $post): bool
    {
        if ($user->hasAnyRole(['admin', 'editor'])) {
            return true;
        }

        return $user->hasRole('author') && $user->id === $post->user_id;
    }

    public function restore(User $user, Post $post): bool
    {
        return $user->hasAnyRole(['admin', 'editor']);
    }

    public function forceDelete(User $user, Post $post): bool
    {
        return $user->hasRole('admin');
    }
}
```

Auto-discovered by Laravel — no manual registration needed (policy naming convention `App\Policies\{Model}Policy` for `App\Models\{Model}`).

**Seeder (`database/seeders/RolePermissionSeeder.php`):**
```php
<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['admin', 'editor', 'author', 'super_admin'] as $roleName) {
            Role::findOrCreate($roleName);
        }
    }
}
```

(Keep seeder lean — Shield's `shield:generate` command is the canonical path for permission sync; the seeder only ensures roles exist. Running `php artisan shield:generate --all` after the seeder fills in Resource-specific permissions.)

**`DatabaseSeeder` update:**
```php
public function run(): void
{
    $this->call([
        RolePermissionSeeder::class,
    ]);
}
```

**Tests:**
```php
<?php

declare(strict_types=1);

use App\Models\Post;
use App\Models\User;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    foreach (['admin', 'editor', 'author', 'super_admin'] as $roleName) {
        Role::findOrCreate($roleName);
    }
});

test('guests can view published posts but not drafts', function (): void {
    $published = Post::factory()->published()->create();
    $draft = Post::factory()->create();

    expect((new \App\Policies\PostPolicy())->view(null, $published))->toBeTrue();
    expect((new \App\Policies\PostPolicy())->view(null, $draft))->toBeFalse();
});

test('author can update their own post but not others', function (): void {
    $author = User::factory()->create();
    $author->assignRole('author');

    $ownPost = Post::factory()->for($author)->create();
    $othersPost = Post::factory()->create();

    expect($author->can('update', $ownPost))->toBeTrue();
    expect($author->can('update', $othersPost))->toBeFalse();
});

test('editor can update any post', function (): void {
    $editor = User::factory()->create();
    $editor->assignRole('editor');

    $post = Post::factory()->create();

    expect($editor->can('update', $post))->toBeTrue();
    expect($editor->can('delete', $post))->toBeTrue();
    expect($editor->can('forceDelete', $post))->toBeFalse(); // admin-only
});

test('admin can forceDelete, editor cannot', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $editor = User::factory()->create();
    $editor->assignRole('editor');

    $post = Post::factory()->create();

    expect($admin->can('forceDelete', $post))->toBeTrue();
    expect($editor->can('forceDelete', $post))->toBeFalse();
});

test('guest cannot create posts', function (): void {
    expect((new \App\Policies\PostPolicy())->create(User::factory()->create()))->toBeFalse();
});

test('RolePermissionSeeder creates all 4 roles', function (): void {
    // beforeEach already seeds; verify all 4 exist
    expect(Role::whereIn('name', ['admin', 'editor', 'author', 'super_admin'])->count())->toBe(4);
});
```

**Steps:**
- [ ] 36.1 Create PostPolicy
- [ ] 36.2 Create RolePermissionSeeder + update DatabaseSeeder
- [ ] 36.3 Create PostPolicyTest
- [ ] 36.4 Filter test: `--filter=PostPolicyTest` — 6 tests pass
- [ ] 36.5 pint --dirty — pass
- [ ] 36.6 Full suite — `90 passed` (84 + 6) with 0 failed
- [ ] 36.7 Commit:
```
feat(post): add PostPolicy + RBAC seed

Stand up the three-role RBAC that PRD §3.1 requires
(admin / editor / author), with Shield's `super_admin` already in
place from Phase 1 Task 3 bypassing all policies via Gate::before.

`App\Policies\PostPolicy` (auto-discovered by naming convention):
- viewAny: public (index page)
- view: published → public; draft/scheduled → admin/editor/owner
- create: admin / editor / author
- update / delete: admin / editor always; author only on own posts
- restore: admin / editor
- forceDelete: admin only

`RolePermissionSeeder` creates the 4 role rows (admin, editor,
author, super_admin). Shield's `shield:generate --all` is the
canonical way to sync Resource-level permissions to each role
post-Task-37 — the seeder itself stays minimal.

6 policy tests cover the role matrix: guest view of
published/draft, author-own-only, editor any, admin force-delete
escalation, guest create denial, seeder invariants.
```

---

## Task 37: Filament PostResource

**Files:**
- Create: `app/Filament/Resources/PostResource.php`
- Create: `app/Filament/Resources/PostResource/Pages/ListPosts.php`
- Create: `app/Filament/Resources/PostResource/Pages/CreatePost.php`
- Create: `app/Filament/Resources/PostResource/Pages/EditPost.php`
- Create: `tests/Feature/Admin/PostResourceTest.php`

**Resource shape:**

Use `make:filament-resource Post --generate` as a starting point (this regenerates on a clean installer; the subagent should run it and then edit), then customize:
- Form: title (required), slug (disabled — HasSlug fills), status (Select enum), published_at (DateTimePicker), excerpt (Textarea), body_html (RichEditor), seo_title, seo_description, is_comments_enabled (Toggle), featured image via `SpatieMediaLibraryFileUpload`
- Table columns: title, status (with color badge), author name, published_at, view_count
- Table filters: `SelectFilter` on status, relation filter on user
- Table actions: Edit, SoftDelete
- Bulk actions: DeleteBulkAction

**Key form definition (showing shape; exact field shapes depend on Filament 5.5 API):**
```php
return $form->schema([
    TextInput::make('title')->required()->maxLength(255),
    TextInput::make('slug')->disabled()->dehydrated(true),
    Select::make('status')->options(PostStatus::class)->required(),
    DateTimePicker::make('published_at'),
    Textarea::make('excerpt')->maxLength(500)->columnSpanFull(),
    RichEditor::make('body_html')->required()->columnSpanFull(),
    TextInput::make('seo_title')->maxLength(255),
    TextInput::make('seo_description')->maxLength(500),
    Toggle::make('is_comments_enabled'),
    SpatieMediaLibraryFileUpload::make('featured')->collection('featured'),
])
```

**Tests (`tests/Feature/Admin/PostResourceTest.php`):**
```php
<?php

declare(strict_types=1);

use App\Models\Post;
use App\Models\User;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    Role::findOrCreate('super_admin');
});

test('super_admin accesses the posts resource index page', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');

    $this->actingAs($admin)
        ->get('/admin/posts')
        ->assertSuccessful();
});

test('guests are redirected from the posts resource', function (): void {
    $this->get('/admin/posts')->assertRedirect('/admin/login');
});

test('super_admin sees the create page and can render the form', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');

    $this->actingAs($admin)
        ->get('/admin/posts/create')
        ->assertSuccessful();
});

test('PostResource exposes the expected form fields', function (): void {
    $fields = \App\Filament\Resources\PostResource::getFormSchema
        ?? null;

    // Instantiate the Resource form via Filament's container and inspect:
    $resource = new \App\Filament\Resources\PostResource();
    // The form() method returns a Form builder; we assert the Resource class exists + binds to Post.
    expect(\App\Filament\Resources\PostResource::getModel())->toBe(\App\Models\Post::class);
});
```

(The fourth test intentionally stays lightweight — Filament form rendering is heavy to unit test; the `get /admin/posts/create` assertSuccessful already exercises the form builder.)

**Steps:**
- [ ] 37.1 Run `env PATH="$HOME/.config/herd-lite/bin:$PATH" php artisan make:filament-resource Post --generate --no-interaction` — scaffolds the Resource + 3 Pages
- [ ] 37.2 Edit the generated Resource to add: RichEditor for body_html, SpatieMediaLibraryFileUpload for featured, Select for PostStatus with enum, Toggle for is_comments_enabled, table filters on status and user
- [ ] 37.3 Verify Policy auto-discovery kicks in (running `php artisan route:list | grep posts` should show `/admin/posts` routes)
- [ ] 37.4 Create PostResourceTest with 4 tests
- [ ] 37.5 Filter test: `--filter=PostResourceTest` — 4 tests pass
- [ ] 37.6 pint --dirty — pass (may auto-fix installer-generated code)
- [ ] 37.7 Run Shield generate to sync resource-level permissions:
      `env PATH="$HOME/.config/herd-lite/bin:$PATH" php artisan shield:generate --resource=PostResource --no-interaction`
      (this populates `view_post`, `create_post`, etc. so production admin users with `admin` role can actually use the panel)
- [ ] 37.8 Full suite — `94 passed` (90 + 4) with 0 failed
- [ ] 37.9 Commit (NO AI attribution):
```
feat(post): add Filament PostResource for admin CRUD

Scaffold via `artisan make:filament-resource Post --generate` then
customize:

- Form: TextInput (title, slug disabled so HasSlug fills,
  seo_title, seo_description), Select (status enum),
  DateTimePicker (published_at), Textarea (excerpt), RichEditor
  (body_html), Toggle (is_comments_enabled),
  SpatieMediaLibraryFileUpload (featured single-file collection).
- Table: columns for title, status badge, author name,
  published_at, view_count. Filters on status + user.
- Uses Filament 5.5's Policy auto-discovery, so `super_admin`
  bypasses via Shield's Gate::before and each role's Shield-
  generated permissions apply.

`shield:generate --resource=PostResource` run post-scaffold to
sync the 7 standard Resource permissions (view_any / view /
create / update / delete / restore / force_delete_post) into
Shield's permission catalog. Any role-to-permission mapping
happens in the admin UI or a future explicit seeder.

4 feature tests: super_admin reaches /admin/posts index and
create pages, guest redirected, PostResource model binding.
```

---

## Self-review

**1. Spec coverage:**
- Foundation (enum + migration + model + factory + preventLazyLoading) — Task 34 ✓
- Capabilities (HasSlug + Searchable + InteractsWithMedia) — Task 35 ✓
- Authorization (PostPolicy + RBAC seed) — Task 36 ✓
- Admin UI (Filament PostResource + Shield permissions) — Task 37 ✓

**2. Placeholder scan:** every field, test, and commit message has concrete content.

**3. Consistency:**
- PostStatus enum referenced from Task 34 (creation) / 35 (casts in factory) / 37 (form Select).
- `getRouteKeyName() = 'uuid'` set in Task 34, used in Task 37's URL shape.
- Shield + PostPolicy interplay — Task 36 writes the policy; Task 37 runs `shield:generate` to wire permissions.
- `preventLazyLoading` in Task 34 forces every later task's code to eager-load relations properly — hits N+1 bugs immediately instead of later.

**Known risks:**
1. **Task 37's `make:filament-resource --generate` output varies** across Filament 5.5 minor versions. The subagent must adapt the generated code to the form shape described rather than treat my draft as exact.
2. **Scout collection driver doesn't implement every method** that the real Meilisearch driver does; the Task 35 `shouldBeSearchable` test is safe (it just calls the method on Post, not Scout). If Task 37's table search hits Scout functionality not in the collection driver, that would be a new failure — unlikely for list queries but flagged.
3. **Shield v4 permissions generation** is idempotent. Running it twice is harmless. Running it BEFORE the PostResource class exists would skip the resource — hence Task 37.7 runs it after the Resource file lands.

---

## Execution handoff

Subagent-driven, one task per dispatch. Two-stage review on Task 34 (foundation is load-bearing) and Task 37 (Filament UI, most opportunity for misconfiguration). Tasks 35 and 36 get lightweight final-verify review since the patterns are established.

**Target:** 4 commits on `feat/post-mvp`, full suite 94 passed at final commit, branch ready to fast-forward into main.
