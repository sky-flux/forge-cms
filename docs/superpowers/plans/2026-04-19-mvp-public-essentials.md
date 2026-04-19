# MVP Public Essentials — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development. Each task = 1 commit. Implementer never commits.

**Worktree:** `.worktrees/mvp-public-essentials` on `feat/mvp-public-essentials` (branches from main).
**Tech:** Laravel 13, Inertia 3 (React), Filament 5, Scout 11 (Meilisearch), spatie/laravel-feed, spatie/laravel-sitemap, Pest 4.
**Spec:** `docs/superpowers/specs/2026-04-19-mvp-completion-batch-1.md`.

## Workflow per Task

1. Write failing Pest test(s) first → run → confirm RED
2. Minimal implementation → run tests → confirm GREEN
3. `vendor/bin/pint --dirty --format agent`
4. Controller dispatches `pr-review-toolkit:code-reviewer` → if issues, implementer fixes, re-review loop
5. Controller commits with exact message in Task section
6. Next Task

## Project Conventions (strict)

- `declare(strict_types=1);` on every PHP file
- Pest: `test('...')` + `$this->actingAs(...)->get(...)` + `->assertInertia(fn ($page) => ...)` — NO `use function Pest\Laravel\...`
- Web controllers: return `Inertia::render(...)`, NEVER Blade
- Always wrap Inertia props in a Resource (e.g. `PostResource::collection($posts)`)
- Eager-load for preventLazyLoading: `->with('user', 'categories', 'tags')` on any Post query touched
- Run tests: `php artisan test --compact --filter=<Name>`
- Admin tests need `$this->withoutVite()` in beforeEach — Web tests hitting Inertia pages may also need this (check sibling PostPageTest for pattern)

## Pre-flight commands for implementer

```bash
cd /Users/martinadamsdev/workspace/forge-cms/.worktrees/mvp-public-essentials
grep -n 'Searchable\|toSearchableArray\|shouldBeSearchable' app/Models/Post.php  # model pattern
cat routes/web.php  # current routes to append to
```

---

### Task 1 — Search endpoint + page

**Files:**
- Modify: `app/Models/Page.php` (add Searchable trait + 2 methods mirroring Post)
- Create: `app/Http/Controllers/Web/SearchController.php`
- Create: `resources/js/pages/Search.tsx`
- Modify: `routes/web.php` (add `/search` route)
- Create: `tests/Feature/Web/SearchTest.php`

#### TDD

**Step 1 — failing test.** Create `tests/Feature/Web/SearchTest.php`:

```php
<?php

declare(strict_types=1);

use App\Models\Page;
use App\Models\Post;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->withoutVite();
});

test('search page renders empty state when q is absent', function (): void {
    $this->get('/search')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Search')
            ->where('query', null)
            ->where('posts.data', [])
            ->where('pages.data', [])
        );
});

test('search returns matching published posts', function (): void {
    $match = Post::factory()->published()->create(['title' => 'Unique Laravel Tip']);
    Post::factory()->published()->create(['title' => 'Unrelated']);

    $this->get('/search?q=Laravel')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Search')
            ->where('query', 'Laravel')
            ->has('posts.data', 1)
            ->where('posts.data.0.title', $match->title)
        );
});

test('search returns matching published pages', function (): void {
    $page = Page::factory()->published()->create(['title' => 'About Forge']);

    $this->get('/search?q=Forge')
        ->assertOk()
        ->assertInertia(fn (Assert $inertia) => $inertia
            ->component('Search')
            ->has('pages.data', 1)
            ->where('pages.data.0.title', $page->title)
        );
});
```

**Step 2.** Run — expect RED:
```bash
php artisan test --compact --filter=SearchTest
```

**Step 3 — implement `Page` Searchable.** In `app/Models/Page.php`, mirror `Post.php:111-` pattern:

```php
use Laravel\Scout\Searchable;

// Add to `use` traits list on the class: add `Searchable,`

/**
 * @return array<string, mixed>
 */
public function toSearchableArray(): array
{
    return [
        'id' => $this->id,
        'title' => (string) $this->title,
        'excerpt' => (string) ($this->excerpt ?? ''),
        'body_text' => (string) strip_tags((string) $this->body_html),
        'slug' => (string) $this->slug,
    ];
}

public function shouldBeSearchable(): bool
{
    return $this->status === \App\Enums\PageStatus::Published;
}
```

(If `PageStatus::Published` doesn't exist, match whatever the Page enum/column uses — grep `app/Enums/PageStatus.php` first.)

**Step 4 — SearchController.** Create:

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Resources\PostResource;
use App\Models\Page;
use App\Models\Post;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SearchController extends Controller
{
    public function index(Request $request): Response
    {
        $query = $request->string('q')->toString() ?: null;

        $posts = $query
            ? Post::search($query)->paginate(10)->withQueryString()
            : Post::query()->whereRaw('1=0')->paginate(10);

        $pages = $query
            ? Page::search($query)->paginate(10)->withQueryString()
            : Page::query()->whereRaw('1=0')->paginate(10);

        return Inertia::render('Search', [
            'query' => $query,
            'posts' => PostResource::collection($posts),
            'pages' => $pages, // if PageResource exists, use it; otherwise raw
        ]);
    }
}
```

(Check if `App\Http\Resources\PageResource` exists; if yes, wrap pages too. If not, pass `$pages` raw — the test asserts `pages.data.0.title` which works with raw paginator too.)

**Step 5 — route.** Append to `routes/web.php` under the `Route::get('/tags/...')` line:

```php
Route::get('/search', [App\Http\Controllers\Web\SearchController::class, 'index'])->name('search');
```

**Step 6 — Inertia page.** Create `resources/js/pages/Search.tsx`:

```tsx
import { Head, Link, useForm } from '@inertiajs/react';
import PublicLayout from '@/layouts/PublicLayout';
import type { Post } from '@/types/post';

type Props = {
    query: string | null;
    posts: { data: Post[] };
    pages: { data: Array<{ id: number; title: string; slug: string; excerpt?: string | null }> };
};

export default function Search({ query, posts, pages }: Props) {
    const form = useForm({ q: query ?? '' });

    return (
        <PublicLayout>
            <Head title={query ? `Search: ${query}` : 'Search'} />
            <section className="mx-auto max-w-3xl px-4 py-10">
                <h1 className="text-3xl font-semibold">Search</h1>
                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        form.get('/search');
                    }}
                    className="mt-4 flex gap-2"
                >
                    <input
                        type="text"
                        name="q"
                        value={form.data.q}
                        onChange={(e) => form.setData('q', e.target.value)}
                        placeholder="Search posts and pages…"
                        className="flex-1 rounded border px-3 py-2"
                    />
                    <button type="submit" className="rounded bg-black px-4 py-2 text-white">
                        Go
                    </button>
                </form>

                {query === null ? (
                    <p className="mt-8 text-gray-500">Enter a query above.</p>
                ) : (
                    <div className="mt-8 space-y-8">
                        <section>
                            <h2 className="text-lg font-medium">Posts ({posts.data.length})</h2>
                            <ul className="mt-2 space-y-2">
                                {posts.data.map((post) => (
                                    <li key={post.id}>
                                        <Link href={`/posts/${post.slug}`} className="font-medium underline">
                                            {post.title}
                                        </Link>
                                    </li>
                                ))}
                            </ul>
                        </section>
                        <section>
                            <h2 className="text-lg font-medium">Pages ({pages.data.length})</h2>
                            <ul className="mt-2 space-y-2">
                                {pages.data.map((page) => (
                                    <li key={page.id}>
                                        <Link href={`/pages/${page.slug}`} className="font-medium underline">
                                            {page.title}
                                        </Link>
                                    </li>
                                ))}
                            </ul>
                        </section>
                    </div>
                )}
            </section>
        </PublicLayout>
    );
}
```

(If `PublicLayout` import path differs, match sibling `resources/js/pages/Posts/Index.tsx`.)

**Step 7.** Run — expect GREEN:
```bash
php artisan test --compact --filter=SearchTest
```

If using Scout `collection` driver (default in testing), `Model::search()` does in-memory filtering. Tests should pass without Meilisearch.

**Step 8 — Pint.**
```bash
vendor/bin/pint --dirty --format agent
```

**Step 9 — Controller CR + commit.** Message:
```
feat(web): add /search endpoint over Scout-indexed Posts and Pages
```

---

### Task 2 — RSS Feed

**Files:**
- Modify: `config/feed.php` (populate items/url/title/description)
- Modify: `app/Models/Post.php` (add `getFeedItems(): Collection` + `toFeedItem(): FeedItem`)
- Modify: `routes/web.php` (register spatie feed route)
- Create: `tests/Feature/Web/FeedTest.php`

#### TDD

**Step 1 — failing test.** Create `tests/Feature/Web/FeedTest.php`:

```php
<?php

declare(strict_types=1);

use App\Models\Post;

test('feed.xml returns valid RSS with published posts', function (): void {
    $post = Post::factory()->published()->create(['title' => 'Hello Feed']);
    Post::factory()->create(['status' => \App\Enums\PostStatus::Draft]); // should not appear

    $response = $this->get('/feed.xml');

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('xml');

    $body = $response->getContent();
    expect($body)->toContain('<rss')
        ->and($body)->toContain('Hello Feed');
});
```

**Step 2.** Run — expect RED.

**Step 3 — populate `config/feed.php` `feeds.main`:** open the file, replace the `main` feed block:

```php
'feeds' => [
    'main' => [
        'items' => [App\Models\Post::class, 'getFeedItems'],
        'url' => '/feed.xml',
        'title' => config('app.name') . ' — Latest Posts',
        'description' => 'Latest articles from ' . config('app.name'),
        'language' => 'en-US',
        'format' => 'rss',
        'view' => 'feed::rss',
        'type' => 'application/rss+xml',
        'contentType' => '',
    ],
],
```

**Step 4 — `Post` model methods.** Add at end of class body:

```php
/**
 * @return \Illuminate\Support\Collection<int, \App\Models\Post>
 */
public static function getFeedItems(): \Illuminate\Support\Collection
{
    return self::query()
        ->where('status', \App\Enums\PostStatus::Published)
        ->where('published_at', '<=', now())
        ->with('user')
        ->orderByDesc('published_at')
        ->limit(50)
        ->get();
}

public function toFeedItem(): \Spatie\Feed\FeedItem
{
    return \Spatie\Feed\FeedItem::create()
        ->id((string) $this->getRouteKey())
        ->title((string) $this->title)
        ->summary((string) ($this->excerpt ?? ''))
        ->updated($this->updated_at ?? now())
        ->link(route('posts.show', ['post' => $this]))
        ->authorName((string) ($this->user?->name ?? 'Anonymous'))
        ->authorEmail((string) ($this->user?->email ?? 'noreply@example.com'));
}
```

**Step 5 — register feed route.** Append to `routes/web.php`:

```php
Route::feeds();
```

If `Route::feeds` doesn't exist as a macro, fall back to explicit:

```php
Route::get('/feed.xml', [\Spatie\Feed\Http\FeedController::class, '__invoke'])
    ->defaults('feed', 'main')
    ->name('feeds.main');
```

Check which syntax the installed version supports: `grep -n 'feeds\|FeedController' vendor/spatie/laravel-feed/src/FeedServiceProvider.php`.

**Step 6.** Run — expect GREEN.

**Step 7 — Pint + CR + commit.** Message:
```
feat(web): publish Post RSS feed at /feed.xml
```

---

### Task 3 — Sitemap

**Files:**
- Create: `app/Http/Controllers/Web/SitemapController.php`
- Modify: `routes/web.php`
- Create: `tests/Feature/Web/SitemapTest.php`

#### TDD

**Step 1 — failing test.**

```php
<?php

declare(strict_types=1);

use App\Models\Category;
use App\Models\Page;
use App\Models\Post;
use App\Models\Tag;

test('sitemap.xml returns a valid sitemap with home, posts, pages, categories, tags', function (): void {
    $post = Post::factory()->published()->create(['slug' => 'hello-world']);
    $page = Page::factory()->published()->create(['slug' => 'about']);
    $category = Category::factory()->create(['slug' => 'news']);
    $tag = Tag::factory()->create(['slug' => 'laravel']);

    $response = $this->get('/sitemap.xml');

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('xml');

    $body = $response->getContent();
    expect($body)->toContain('<urlset')
        ->and($body)->toContain(url('/'))
        ->and($body)->toContain(route('posts.show', ['post' => $post]))
        ->and($body)->toContain(route('pages.show', ['page' => $page]))
        ->and($body)->toContain(route('categories.show', ['category' => $category]))
        ->and($body)->toContain(route('tags.show', ['tag' => $tag]));
});
```

**Step 2.** Run — expect RED.

**Step 3 — controller.**

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Page;
use App\Models\Post;
use App\Models\Tag;
use Illuminate\Http\Response;
use Spatie\Sitemap\Sitemap;
use Spatie\Sitemap\Tags\Url;

class SitemapController extends Controller
{
    public function __invoke(): Response
    {
        $sitemap = Sitemap::create()
            ->add(Url::create(url('/')))
            ->add(Url::create(route('posts.index')));

        Post::query()
            ->where('status', \App\Enums\PostStatus::Published)
            ->where('published_at', '<=', now())
            ->get()
            ->each(fn (Post $post) => $sitemap->add(Url::create(route('posts.show', ['post' => $post]))
                ->setLastModificationDate($post->updated_at ?? now())
            ));

        Page::query()
            ->where('status', \App\Enums\PageStatus::Published)
            ->get()
            ->each(fn (Page $page) => $sitemap->add(Url::create(route('pages.show', ['page' => $page]))
                ->setLastModificationDate($page->updated_at ?? now())
            ));

        Category::all()->each(fn (Category $c) => $sitemap->add(route('categories.show', ['category' => $c])));
        Tag::all()->each(fn (Tag $t) => $sitemap->add(route('tags.show', ['tag' => $t])));

        return response($sitemap->render(), 200, ['Content-Type' => 'application/xml']);
    }
}
```

**Step 4 — route.** Append:

```php
Route::get('/sitemap.xml', App\Http\Controllers\Web\SitemapController::class)->name('sitemap');
```

**Step 5.** Run — expect GREEN.

**Step 6 — Pint + CR + commit.** Message:
```
feat(web): publish dynamic /sitemap.xml covering posts/pages/categories/tags
```

---

### Task 4 — OG image + canonical meta tags

**Files:**
- Modify: `resources/js/pages/Posts/Show.tsx`, `Pages/Show.tsx`, `Home.tsx`, `Categories/Show.tsx`, `Tags/Show.tsx`, `Search.tsx`
- Create: `tests/Feature/Web/SeoMetaTest.php`

Each page's `<Head>` component should include:
```tsx
<Head title={seoTitle}>
  <meta name="description" content={seoDescription} />
  <meta property="og:title" content={seoTitle} />
  <meta property="og:description" content={seoDescription} />
  <meta property="og:image" content={ogImage} />
  <meta property="og:url" content={canonical} />
  <link rel="canonical" href={canonical} />
</Head>
```

Where `ogImage` is either `post.featured_image?.url` (if Post/Page) or `settings.default_og_image`, and `canonical` is `window.location.origin + location.pathname` OR a prop from backend (preferred — pass from controller).

**Controller change** — include `canonical` + `ogImage` in Inertia::render props for each Web controller. Backend derives from `route(...)` + `GeneralSettings::default_og_image`.

#### TDD

```php
<?php

declare(strict_types=1);

use App\Models\Post;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->withoutVite();
});

test('post show page exposes canonical and og:image props', function (): void {
    $post = Post::factory()->published()->create(['slug' => 'hello']);

    $this->get(route('posts.show', ['post' => $post]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Posts/Show')
            ->has('canonical')
            ->where('canonical', route('posts.show', ['post' => $post]))
            ->has('ogImage')
        );
});
```

Then add similar for pages/home/categories/tags/search.

**Step 2 — implement:** add `'canonical' => route(...), 'ogImage' => app(App\Settings\GeneralSettings::class)->default_og_image` to each `Inertia::render` call in the 6 controllers.

**Step 3 — update the 6 React pages:** add the meta tags block using the new props.

**Step 4 — Pint + CR + commit.** Message:
```
feat(seo): add og:image + canonical meta tags to all public pages
```

---

## Self-Review

- Tasks are sequential within this worktree (all touch `routes/web.php` or shared controllers)
- Each task has concrete red test → impl → green test → pint → CR → commit
- 4 commits total on `feat/mvp-public-essentials`
- Merges cleanly to main; no file conflicts with the parallel `feat/mvp-scheduled-publisher` worktree
