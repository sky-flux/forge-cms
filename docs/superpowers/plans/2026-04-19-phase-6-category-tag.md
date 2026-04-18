# Phase 6: Category + Tag Implementation Plan

> superpowers:subagent-driven-development. 6-step TDD cycle. Full suite 0 failed before every commit.

**Goal:** Categorize and tag Posts. Visitors browse `/categories/{slug}` and `/tags/{slug}` to see posts in each. Admins CRUD categories (hierarchical) and tags (flat) in Filament. Post editor gets multi-select pickers. Baseline after Phase 5: 124 tests green.

**Architecture:** 3 tasks. v1.0 single-language, so schemas are flat tables (no `*_translations` subtables — those were deferred to v1.x in Phase 2 Task 28). Category has `parent_id` self-FK for 3-level nesting.

**Working dir:** `.worktrees/category-tag` on `feat/category-tag`.

---

## Pre-flight

- HEAD: `42d8f59`
- Full suite: `124 passed, 0 failed`

---

## Task 44: Foundation — Category + Tag + pivots + policies

**Files:**
- Create migrations: `*_create_categories_table`, `*_create_tags_table`, `*_create_post_category_table`, `*_create_post_tag_table`
- Create models: `app/Models/Category.php`, `app/Models/Tag.php`
- Modify: `app/Models/Post.php` — add `categories()` and `tags()` belongsToMany relationships
- Create factories: `database/factories/{CategoryFactory,TagFactory}.php`
- Create policies: `app/Policies/{CategoryPolicy,TagPolicy}.php`
- Create tests: `tests/Feature/Models/{CategoryTest,TagTest}.php`, `tests/Feature/Policies/{CategoryPolicyTest,TagPolicyTest}.php`

**Category schema:**
```php
Schema::create('categories', function (Blueprint $t): void {
    $t->id();
    $t->uuid('uuid')->unique();
    $t->foreignId('parent_id')->nullable()->constrained('categories')->nullOnDelete();
    $t->string('name', 100);
    $t->string('slug', 100)->unique();
    $t->string('description', 500)->nullable();
    $t->integer('sort_order')->default(0);
    $t->timestampsTz();
    $t->index(['parent_id', 'sort_order']);
});
```

**Tag schema:**
```php
Schema::create('tags', function (Blueprint $t): void {
    $t->id();
    $t->uuid('uuid')->unique();
    $t->string('name', 50);
    $t->string('slug', 50)->unique();
    $t->timestampsTz();
    $t->index('name');
});
```

**post_category pivot:** `post_id` + `category_id` + cascade both sides, composite PK.
**post_tag pivot:** `post_id` + `tag_id` + cascade both sides, composite PK.

**Category model:**
- `HasUuids + HasSlug + HasFactory`
- `getRouteKeyName() = 'slug'`
- `parent()` → `belongsTo(self)`
- `children()` → `hasMany(self, 'parent_id')`
- `posts()` → `belongsToMany(Post::class)`
- `scopeRoots()` — `whereNull('parent_id')`

**Tag model:** `HasUuids + HasSlug + HasFactory`, `getRouteKeyName() = 'slug'`, `posts()` → `belongsToMany(Post::class)`.

**Post model additions:** two new methods `categories()` + `tags()` returning `belongsToMany`.

**Policies:** same role matrix as PostPolicy/PagePolicy (viewAny/view public; create/update/delete admin+editor; author can only on own — but Category/Tag don't have `user_id` owner, so author is equivalent to editor for these; simpler: admin+editor can CRUD, author can create new tags only, delete is admin-only since removing a tag/category affects many posts).

Simpler policy (recommended):
- `viewAny` / `view`: true (public)
- `create`: admin + editor + author
- `update`: admin + editor
- `delete`: admin only

**Tests (12):**
- `CategoryTest` (5): create via factory; slug auto; tree nav (parent/children); posts() relationship attach; scopeRoots filters top-level
- `TagTest` (3): create; slug auto; posts() belongsToMany attach
- `CategoryPolicyTest` (2): editor can update, author cannot; only admin can delete
- `TagPolicyTest` (2): same matrix

**Steps:**
- [ ] 44.1 Create all files
- [ ] 44.2 Filter: `--filter='CategoryTest|TagTest|CategoryPolicyTest|TagPolicyTest'` → 12 passes
- [ ] 44.3 pint + full suite → `136 passed, 0 failed`
- [ ] 44.4 Commit: `feat(taxonomy): add Category + Tag models + pivots + policies`

---

## Task 45: Filament CategoryResource + TagResource + PostForm pickers

**Files:**
- Scaffold: `app/Filament/Resources/Categories/*` (split: Resource + Schemas/CategoryForm + Tables/CategoriesTable + Pages/{List,Create,Edit})
- Scaffold: `app/Filament/Resources/Tags/*` (same split)
- Modify: `app/Filament/Resources/Posts/Schemas/PostForm.php` — append multi-select Categories + Tags fields
- Create tests: `tests/Feature/Admin/{CategoryResourceTest,TagResourceTest}.php`

**CategoryForm:**
- TextInput name (required, 100 max)
- TextInput slug (disabled, HasSlug fills)
- Select parent_id → `relationship('parent', 'name')`, searchable, placeholder "Top-level"
- Textarea description (500 max)
- TextInput sort_order (numeric, default 0)

**CategoriesTable:**
- Columns: name, parent (from relation), sort_order, posts_count (via `withCount('posts')`)
- Filters: parent (relationship select)
- Default sort: `sort_order asc` within each parent group

**TagForm:**
- TextInput name (required, 50 max)
- TextInput slug (disabled)

**TagsTable:**
- name, posts_count, created_at
- Searchable by name

**PostForm additions** (append after existing fields):
```
Select::make('categories')
    ->relationship('categories', 'name')
    ->multiple()
    ->preload()
    ->searchable(),
Select::make('tags')
    ->relationship('tags', 'name')
    ->multiple()
    ->preload()
    ->searchable()
    ->createOptionForm([
        TextInput::make('name')->required()->maxLength(50),
    ]),
```

Filament auto-syncs `sync()` on belongsToMany relationships when the field name matches the relation name.

**Tests:** For each resource: super_admin reaches index + create; guest redirected; Resource::getModel() binding. 6 tests total.

**Steps:**
- [ ] 45.1 Scaffold both resources (`make:filament-resource Category` / `Tag` — no `--generate` flag since it hits DB)
- [ ] 45.2 Customize forms/tables
- [ ] 45.3 Append to PostForm
- [ ] 45.4 Write tests
- [ ] 45.5 pint + full suite → `142 passed, 0 failed` (136 + 6 new)
- [ ] 45.6 Commit

---

## Task 46: Category + Tag archive frontend

**Files:**
- Create: `app/Http/Controllers/Web/{CategoryController,TagController}.php`
- Create: `app/Http/Resources/{CategoryResource,TagResource}.php`
- Modify: `app/Http/Resources/PostResource.php` — include `categories` + `tags` when loaded
- Modify: `routes/web.php` — add `/categories/{category:slug}` + `/tags/{tag:slug}`
- Create tests: `tests/Feature/Web/{CategoryArchiveTest,TagArchiveTest}.php`
- Create types: `resources/js/types/{category.ts,tag.ts}`
- Create pages: `resources/js/pages/Categories/Show.tsx`, `resources/js/pages/Tags/Show.tsx`

**Controllers:**
Both shows paginate their relationship:
```php
public function show(Category $category): Response
{
    $posts = $category->posts()->published()->with('user:id,name')->paginate(12);
    return Inertia::render('Categories/Show', [
        'category' => new CategoryResource($category),
        'posts' => PostResource::collection($posts),
    ]);
}
```

(TagController mirrors.)

**Tests (8):** each archive — guest sees category's published posts only; empty category renders; post appearing in multiple categories shows in each.

**Inertia pages:** Category / Tag archive shows the taxonomy name + description (for category) then a list of posts (similar shape to `Posts/Index.tsx` but scoped).

**Wayfinder regen** after routes land.

**Steps:**
- [ ] 46.1 Backend + tests
- [ ] 46.2 Wayfinder regen
- [ ] 46.3 Frontend files
- [ ] 46.4 types:check + pint + full suite → `150 passed, 0 failed`
- [ ] 46.5 Commit

---

## Self-review

- Spec coverage: Category + Tag models (44) + admin (45) + archive frontend (46). ✓
- No `Searchable` trait on Category/Tag — Scout-indexing taxonomies isn't in scope for v1.0.
- Filament relationship-field convention syncs pivots automatically; manual `sync()` code in controllers not needed.

**Known risks:**
1. `make:filament-resource --generate` tries DB at scaffold (Phase 5 Task 42 learning) — use without `--generate`, write form/table bodies manually.
2. Category's `parent_id` self-FK could cause infinite recursion if a category's ancestor chain loops. Application-layer validation on save needed — defer to a follow-up commit (or catch in Task 45 if trivial).
3. Tag `createOptionForm` lets editors create new tags on the fly while editing a post — good UX but means tag creation bypasses the tag admin's rate-limit / moderation if we ever add those. Fine for MVP.
