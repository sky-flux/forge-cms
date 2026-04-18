# Phase 4: Post Public Frontend Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: superpowers:subagent-driven-development. Every task follows the 6-step TDD cycle in session memory: 写失败测试 → 确认红 → 实现 → 确认绿 → pint --dirty + 全量测试 → commit.

**Goal:** Make the Posts written in Phase 3's admin reachable from the public web — guest can browse published posts at `/posts` and read a single post at `/posts/{uuid}`. Draft / scheduled posts stay invisible to guests; owner authors and editors see their own drafts.

**Architecture:** Thin Controller + API Resource + Inertia page components. No business logic in the Controller — just `Inertia::render` + `paginate`. Controller trusts `PostPolicy::view` for draft access (Phase 3 Task 36) and `Gate::before` for super_admin bypass (Phase 3 Task 38). Wayfinder regenerates after the Controller lands so Inertia pages import typed route functions instead of hardcoding URLs.

**Tech context:** Laravel 13 / Inertia v3 / React 19 / Tailwind 4 / shadcn/ui. `resources/js/actions/` and `resources/js/routes/` are gitignored (wayfinder output regenerated per developer). `resources/js/pages/` uses lowercase per starter convention.

**Working directory:** `.worktrees/post-frontend` on branch `feat/post-frontend`.

---

## Pre-flight

- [ ] `git branch --show-current` → `feat/post-frontend`
- [ ] HEAD: `6cfb57a` (Phase 3 merge tip)
- [ ] Full suite: `97 passed (239 assertions), 0 failed`
- [ ] `node_modules` symlinked from parent

---

## Task 39: Web\PostController + routes + PostResource + wayfinder

**Files:**
- Create: `app/Http/Controllers/Web/PostController.php`
- Create: `app/Http/Resources/PostResource.php`
- Modify: `routes/web.php`
- Regenerate (gitignored): `resources/js/actions/`, `resources/js/routes/`
- Create: `tests/Feature/Web/PostPageTest.php`

**Controller shape:** index paginates `Post::published()->with('user:id,name')->orderBy('published_at', 'desc')->paginate(12)`; show uses route model binding on uuid (set via `getRouteKeyName` in Phase 3 Task 34), calls `$this->authorize('view', $post)`, eager loads `user:id,name`, increments `view_count`, renders `Inertia::render('Posts/Show', ['post' => new PostResource($post)])`.

**Resource shape (camelCase, frontend convention):** `uuid`, `title`, `slug`, `excerpt`, `bodyHtml` (via `$this->when($request->routeIs('posts.show'), ...)` so the list payload stays lean), `status`, `publishedAt`, `viewCount`, `author.name` (via `whenLoaded('user')`), `seoTitle`, `seoDescription`.

**Tests (6):** guest sees only published on index; guest reads published show; guest 403 on draft; author previews own draft (actingAs + role); view_count increments; index does NOT expose body html in list payload.

**Steps:**
- [x] 39.1 Create 4 files
- [x] 39.2 `env PATH="$HOME/.config/herd-lite/bin:$PATH" php artisan wayfinder:generate --with-form --no-interaction`
- [x] 39.3 Filter: 6 passes
- [x] 39.4 pint + full suite: `103 passed, 0 failed`
- [x] 39.5 Commit: `feat(post): add web PostController + Inertia PostResource`

---

## Task 40: Inertia Pages/Posts + TypeScript types + unwrap JsonResource

Task 39 discovered that Laravel 11+ `JsonResource` wraps a single resource as `{data: {...}}` by default. Task 40 globally disables the wrap via `JsonResource::withoutWrapping()` in `AppServiceProvider::boot()` so the frontend sees flat `post.uuid` instead of `post.data.uuid`. The one adjusted assertion in Task 39's test (`post.data.*`) is reverted to flat `post.*`. Paginator collections still ship as `{data, links, meta}` — that's a separate Laravel paginator contract not affected by `withoutWrapping`.

**Files:**
- Modify: `app/Providers/AppServiceProvider.php` — add `JsonResource::withoutWrapping()` call in `boot()`
- Modify: `tests/Feature/Web/PostPageTest.php` — revert the 3 `post.data.*` assertions
- Create: `resources/js/types/post.ts` — `Post`, `PostAuthor`, `Paginated<T>` interfaces
- Create: `resources/js/pages/Posts/Index.tsx` — paginated list with shadcn prose + wayfinder `postShow` Links
- Create: `resources/js/pages/Posts/Show.tsx` — title + byline + body HTML rendered via React's raw-HTML injection API

**Show page body handling:** body html comes from Filament RichEditor which is admin-trusted input. Render via React's raw-HTML prop. Inline code comment documents the admin-trust justification + the DOMPurify fallback if this surface ever accepts user-submitted HTML.

**Steps:**
- [x] 40.1 AppServiceProvider + test revert + 3 frontend files
- [x] 40.2 Verify wayfinder output exists (`resources/js/routes/posts/index.ts`)
- [x] 40.3 `bun run types:check` → 0 errors
- [x] 40.4 pint + full suite: `103 passed, 0 failed`
- [x] 40.5 Commit: `feat(post): add Inertia Pages/Posts + unwrap JsonResource globally`

---

## Discoveries worth memoising

1. **Laravel 11+ base Controller dropped `AuthorizesRequests`.** Must add `use Illuminate\Foundation\Auth\Access\AuthorizesRequests;` + `use AuthorizesRequests;` explicitly in any Controller that calls `$this->authorize()`.
2. **Inertia + Pest 4 test env needs `withoutVite()` + `->component(name, false)`.** First argument is the component name; second is the "check-page-file-exists" flag. Without `false`, Inertia's view finder fails because the .tsx hasn't been rendered-compiled yet.
3. **`JsonResource` default wrapping is `{data: ...}` in Laravel 11+.** Disable globally via `JsonResource::withoutWrapping()` in a fresh app; re-enable per-resource if a future resource needs the wrap explicitly.
4. **Wayfinder output is gitignored.** Fresh clones need `php artisan wayfinder:generate` before `bun run types:check` passes. Worth a note in setup.md eventually.
