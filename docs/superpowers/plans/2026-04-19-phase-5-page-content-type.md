# Phase 5: Page Content Type Implementation Plan

> **For agentic workers:** superpowers:subagent-driven-development. 6-step TDD cycle per commit. Full suite 0 failed before every commit.

**Goal:** Ship the Page content type as a sibling to Phase 3's Post. Same Filament + RBAC + Inertia pattern, but with slug-bound routing (pages are accessed by slug like `/pages/about-us`, not uuid) and no public index (pages are referenced from navigation, not browsed).

**Architecture:** 3 commits. Reuse patterns from Phase 3 Task 34/36/37 (Post foundation / Policy / Filament Resource) and Phase 4 Task 39 (Web Controller + JsonResource). Differences from Post:
- Route-model binding by `slug` not `uuid`
- `is_homepage` + `sort_order` fields
- No public index — only `show`
- `PostStatus` enum reused (Draft/Published/Scheduled)

**Tech context:** Laravel 13 / Filament 5.5 / Inertia v3 / React 19. `JsonResource::withoutWrapping()` already set in AppServiceProvider (Phase 4 Task 40). Super_admin `Gate::before` active (Phase 3 Task 38). `preventLazyLoading` outside production.

**Working directory:** `.worktrees/page-content` on branch `feat/page-content-type`.

---

## Pre-flight

- [ ] HEAD: `08c3889`
- [ ] Full suite: `103 passed (278 assertions), 0 failed`

---

## Task 41: Page foundation — model + migration + factory + policy + tests

**Files:**
- Create: `database/migrations/*_create_pages_table.php`
- Create: `app/Models/Page.php`
- Create: `database/factories/PageFactory.php`
- Create: `app/Policies/PagePolicy.php`
- Create: `tests/Feature/Models/PageTest.php`
- Create: `tests/Feature/Policies/PagePolicyTest.php`

**Migration schema (matches `docs/database.md §3.4` post-Phase-2 rewrite):**

```php
Schema::create('pages', function (Blueprint $t): void {
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
    $t->integer('sort_order')->default(0);
    $t->boolean('is_homepage')->default(false);
    $t->boolean('is_comments_enabled')->default(true);
    $t->jsonb('meta')->default('{}');
    $t->softDeletes();
    $t->timestampsTz();
    $t->index(['status', 'published_at']);
    $t->index('sort_order');
});
```

**Model key differences from Post:**
- `getRouteKeyName()` returns `'slug'` (not `'uuid'` — pages route by slug).
- HasSlug + HasUuids + InteractsWithMedia + SoftDeletes + HasFactory. NO `Searchable` (pages aren't in content search by default — add v1.x if needed).
- `$fillable` includes `sort_order`, `is_homepage` on top of Post's list.
- Casts add `is_homepage => boolean`, `sort_order => integer`.
- Scopes: `published()` (same as Post), `homepage()` (`where('is_homepage', true)`).
- `registerMediaCollections` adds `featured` single-file only (no gallery — pages less visual).

**Policy:** byte-copy of PostPolicy semantics for Page. Editor/admin always; author updates own; admin forceDelete. Guest view allowed only when `status === Published`.

**Tests (6 each file, 12 total):**
- `PageTest`: created, slug routeKey, published scope, homepage scope, soft delete cycle, media attach to featured
- `PagePolicyTest`: guest view published vs draft, author edits own only, editor edits any, admin forceDelete, create permission matrix, seeder invariants (reuse the 4-role setUp)

**Steps:**
- [ ] 41.1 Create 6 files
- [ ] 41.2 Filter: `--filter='PageTest|PagePolicyTest'` → 12 passes
- [ ] 41.3 pint + full suite → `115 passed, 0 failed`
- [ ] 41.4 Commit: `feat(page): foundation — model + migration + factory + policy`

---

## Task 42: Filament PageResource

**Files:** Same structure Phase 3 Task 37 used (Filament 5.5 split layout):
- `app/Filament/Resources/Pages/PageResource.php`
- `app/Filament/Resources/Pages/Schemas/PageForm.php`
- `app/Filament/Resources/Pages/Tables/PagesTable.php`
- `app/Filament/Resources/Pages/Pages/{ListPages,CreatePage,EditPage}.php`
- `tests/Feature/Admin/PageResourceTest.php`

**Scaffold:**
```
env PATH="$HOME/.config/herd-lite/bin:$PATH" php artisan make:filament-resource Page --generate --no-interaction
```

**Form customization:**
- TextInput title, slug (disabled, HasSlug fills)
- Select status (PostStatus enum reuse)
- DateTimePicker published_at
- TextInput sort_order (numeric)
- Toggle is_homepage + is_comments_enabled
- Textarea excerpt
- RichEditor body_html
- TextInput seo_title / seo_description
- SpatieMediaLibraryFileUpload featured (single-file)
- Select user_id → author

**Table:** title, status badge, author, is_homepage indicator, sort_order, published_at; filter by status + is_homepage + user.

**Tests (4):** super_admin accesses /admin/pages; guest redirected; create form page renders; PageResource::getModel() === Page::class.

**Note about PostStatus reuse:** Since Page uses the same Draft/Published/Scheduled enum, importing `App\Enums\PostStatus` is fine. If we ever want a separate enum, rename both to a shared `ContentStatus` — defer.

**Note about shield:generate trap:** Phase 3 Task 37 discovered shield:generate OVERWRITES hand-written policies. Do NOT run `shield:generate --resource=PageResource` in this task. Permission sync can be done in the admin UI or a future explicit seeder.

**Steps:**
- [ ] 42.1 Scaffold + customize
- [ ] 42.2 Write 4 feature tests (mirror Phase 3 Task 37 shape)
- [ ] 42.3 Filter → 4 passes
- [ ] 42.4 pint + full suite → `119 passed, 0 failed`
- [ ] 42.5 Commit: `feat(page): add Filament PageResource for admin CRUD`

---

## Task 43: Page frontend — Web controller + Inertia Show

**Files:**
- Create: `app/Http/Controllers/Web/PageController.php`
- Create: `app/Http/Resources/PageResource.php`
- Modify: `routes/web.php`
- Create: `tests/Feature/Web/PageShowTest.php`
- Create: `resources/js/types/page.ts`
- Create: `resources/js/pages/Pages/Show.tsx`

**Controller — show only (no index):**

```php
public function show(Page $page): Response
{
    $this->authorize('view', $page);
    $page->load('user:id,name');

    return Inertia::render('Pages/Show', [
        'page' => new PageResource($page),
    ]);
}
```

(No view_count increment — pages are less "view-count-y" and we can add later if needed.)

**Route** (slug-bound via `{page:slug}`):
```php
Route::get('/pages/{page:slug}', [PageController::class, 'show'])->name('pages.show');
```

**PageResource:** same shape as PostResource but with `sortOrder`, `isHomepage` fields; no `viewCount`. `bodyHtml` only on show route (only one route exists, so just include it unconditionally in this first cut).

**Tests (4):** guest sees published page by slug; 403 on draft; author sees own draft; page binds by slug (URL format).

**Frontend:**
- `types/page.ts`: `Page` + `PageAuthor` (no Paginated needed — we don't ship a public index)
- `pages/Pages/Show.tsx`: nearly identical to `Posts/Show.tsx` but without "back to all posts" nav (pages are accessed via direct URL). Include title + body html + seo meta.

**Wayfinder regen** after routes update: `php artisan wayfinder:generate --with-form --no-interaction`.

**Steps:**
- [ ] 43.1 Backend: controller + resource + routes + test (4 tests)
- [ ] 43.2 Wayfinder regen
- [ ] 43.3 Frontend: types + Show page
- [ ] 43.4 `bun run types:check` → 0 errors
- [ ] 43.5 pint + full suite → `123 passed, 0 failed`
- [ ] 43.6 Commit: `feat(page): add Web PageController + Inertia Pages/Show`

---

## Self-review

1. **Spec coverage:** foundation (41) + admin (42) + frontend (43). Matches the 3-task breakdown proposed.
2. **Reuse vs copy-paste:** Page mirrors Post intentionally. If a future refactor wants a shared `Contentable` trait / contract, that's a v1.x concern — premature abstraction now.
3. **Route-model binding by slug:** Laravel's `{page:slug}` explicit binding overrides `getRouteKeyName`. Be consistent — set both (redundant but self-documenting).

**Known risks:**
1. Shield generate trap — do NOT run it in Task 42 (Phase 3 learning).
2. Post's `Searchable` trait isn't on Page. If a test somewhere iterates "all searchable models" it might break — unlikely but worth noting.
3. Filament 5.5 may scaffold `Resources/Pages/Pages/` which collides naming-wise with its own `Pages/` sub-dir. Inspect generated structure before committing.
