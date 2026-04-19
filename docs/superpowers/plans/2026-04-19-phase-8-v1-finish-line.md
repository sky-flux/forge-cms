# Phase 8: v1.0 Finish Line Implementation Plan

> superpowers:subagent-driven-development. 6-step TDD cycle. Full suite 0 failed before every commit.

**Goal:** Close the last 4 v1.0 P0 items: `/sitemap.xml`, `/feed.xml` (RSS/Atom), homepage, 404 error page. Each is a thin wrapper over packages already installed in Phase 1 (spatie/laravel-sitemap Task 14; spatie/laravel-feed Task 18). No new dependencies. After this phase, every PRD §3.1 P0 row is ✅.

**Working dir:** `.worktrees/v1-finish-line` on `feat/v1-finish-line`. Baseline: 184 passed, 0 failed.

---

## Pre-flight

- HEAD: `c726ec8`
- Full suite: `184 passed, 0 failed`

---

## Task 51: Sitemap + RSS feed backend

**Files:**
- Create: `app/Http/Controllers/Web/SitemapController.php`
- Create: `app/Http/Controllers/Web/FeedController.php`
- Modify: `app/Models/Post.php` — implement `Spatie\Feed\Feedable` + add `toFeedItem()` + static `getFeedItems()` method
- Modify: `config/feed.php` — register a feed that points at `Post::getFeedItems`
- Modify: `routes/web.php` — `GET /sitemap.xml` + `GET /feed.xml`
- Create: `tests/Feature/Web/SitemapTest.php`
- Create: `tests/Feature/Web/FeedTest.php`

**SitemapController** generates XML via spatie/laravel-sitemap at request time (cache later if needed). Includes all:
- Homepage (`/`)
- Published Posts (`/posts/{uuid}`)
- Published Pages (`/pages/{slug}`)
- Category archives (`/categories/{slug}`)
- Tag archives (`/tags/{slug}`)

```php
public function show(): Response
{
    $sitemap = Sitemap::create()
        ->add(Url::create('/')->setLastModificationDate(now()))
        ->add(Url::create('/posts')->setLastModificationDate(now()));

    Post::published()->select(['uuid', 'updated_at'])->chunk(500, function ($posts) use ($sitemap) {
        foreach ($posts as $post) {
            $sitemap->add(
                Url::create("/posts/{$post->uuid}")->setLastModificationDate($post->updated_at)
            );
        }
    });

    Page::published()->select(['slug', 'updated_at'])->chunk(500, function ($pages) use ($sitemap) {
        foreach ($pages as $page) {
            $sitemap->add(
                Url::create("/pages/{$page->slug}")->setLastModificationDate($page->updated_at)
            );
        }
    });

    Category::select(['slug', 'updated_at'])->chunk(500, function ($categories) use ($sitemap) {
        foreach ($categories as $cat) {
            $sitemap->add(
                Url::create("/categories/{$cat->slug}")->setLastModificationDate($cat->updated_at)
            );
        }
    });

    Tag::select(['slug', 'updated_at'])->chunk(500, function ($tags) use ($sitemap) {
        foreach ($tags as $tag) {
            $sitemap->add(
                Url::create("/tags/{$tag->slug}")->setLastModificationDate($tag->updated_at)
            );
        }
    });

    return response($sitemap->render(), 200, ['Content-Type' => 'application/xml']);
}
```

**Post Feedable**:

```php
use Spatie\Feed\Feedable;
use Spatie\Feed\FeedItem;

class Post extends Model implements Feedable, HasMedia
{
    // ... existing ...

    public function toFeedItem(): FeedItem
    {
        return FeedItem::create([
            'id' => $this->uuid,
            'title' => $this->title,
            'summary' => $this->excerpt ?? strip_tags($this->body_html),
            'updated' => $this->updated_at,
            'link' => url("/posts/{$this->uuid}"),
            'authorName' => $this->user?->name ?? 'Anonymous',
            'authorEmail' => $this->user?->email,
        ]);
    }

    public static function getFeedItems()
    {
        return self::published()
            ->with('user:id,name,email')
            ->orderBy('published_at', 'desc')
            ->limit(20)
            ->get();
    }
}
```

**`config/feed.php`** — register a feed pointing at Post::getFeedItems:

Find the `'feeds' => [...]` array and add:
```php
'main' => [
    'items' => [Post::class, 'getFeedItems'],
    'url' => '/feed.xml',
    'title' => 'ForgeCMS Feed',
    'description' => 'Latest published posts.',
    'language' => 'en-US',
    'image' => '',
    'format' => 'atom',
    'view' => 'feed::atom',
    'type' => 'application/atom+xml',
    'contentType' => 'application/atom+xml',
],
```

Spatie's feed package auto-registers the route via its service provider if the feed config is present. No manual route needed for `/feed.xml` — the package's Route helper `Route::feeds()` does it. Call `Route::feeds()` once in `routes/web.php`.

**Routes:**
```php
use App\Http\Controllers\Web\SitemapController;

Route::get('/sitemap.xml', [SitemapController::class, 'show'])->name('sitemap');
Route::feeds(); // spatie/laravel-feed helper registers /feed.xml from config
```

**Tests:**
- `SitemapTest` (4 tests): 200 + XML content type; includes published Posts; excludes drafts; includes category + tag archive URLs.
- `FeedTest` (3 tests): `/feed.xml` returns 200; latest 20 posts in order; excludes draft posts.

**Steps:**
- [ ] 51.1 Create all files
- [ ] 51.2 Filter `--filter='SitemapTest|FeedTest'` — 7 passes
- [ ] 51.3 pint + full suite → `191 passed, 0 failed` (184 + 7)
- [ ] 51.4 Commit: `feat(seo): add /sitemap.xml + /feed.xml for discoverability`

---

## Task 52: Homepage + 404 page

**Files:**
- Create: `app/Http/Controllers/Web/HomeController.php`
- Modify: `routes/web.php` — route `/` to HomeController (replacing the `welcome` view)
- Modify: `bootstrap/app.php` — add `withExceptions(fn ($exceptions) => $exceptions->render(...))` for 404
- Create: `resources/js/pages/Home.tsx` (replace welcome.tsx as the landing)
- Create: `resources/js/pages/Errors/NotFound.tsx`
- (Optional) Delete: `resources/js/pages/welcome.tsx`
- Create: `tests/Feature/Web/HomeTest.php`
- Create: `tests/Feature/Web/NotFoundTest.php`

**HomeController:**
```php
public function index(): Response
{
    $homePage = Page::homepage()->published()->first();
    $posts = Post::published()
        ->with('user:id,name')
        ->orderBy('published_at', 'desc')
        ->limit(5)
        ->get();

    return Inertia::render('Home', [
        'homepage' => $homePage ? new PageResource($homePage) : null,
        'latestPosts' => PostResource::collection($posts),
    ]);
}
```

**Routes:** replace the existing `Route::get('/', fn () => Inertia::render('welcome'))` (or similar starter form) with:
```php
Route::get('/', [HomeController::class, 'index'])->name('home');
```

**404 handler** in `bootstrap/app.php` `withExceptions()`:
```php
->withExceptions(function (Exceptions $exceptions): void {
    $exceptions->render(function (Symfony\Component\HttpKernel\Exception\NotHttpException $e, Request $request) {
        // hmm — the correct exception class is
        // Symfony\Component\HttpKernel\Exception\NotFoundHttpException
        // Match on that + Inertia-only requests to avoid breaking API 404 JSON responses.
        return null; // fall through for non-Inertia
    });

    $exceptions->render(function (Symfony\Component\HttpKernel\Exception\NotFoundHttpException $e, Request $request) {
        if ($request->header('X-Inertia') || $request->acceptsHtml()) {
            return Inertia::render('Errors/NotFound')->toResponse($request)->setStatusCode(404);
        }
        return null;
    });
})
```

**Home.tsx** — minimal shape:
- Head with site title
- If `homepage` prop → render its body_html (admin-picked static homepage page)
- "Latest posts" section: list 5 recent Posts with title + excerpt + author + date
- Footer link to /posts for the full list

**NotFound.tsx:**
- Head `<title>404 — Not Found</title>`
- Big "404" heading
- Body text: "The page you're looking for doesn't exist or has been moved."
- Links: back to `/` (Home) and `/posts` (All posts)

**Tests:**
- `HomeTest` (4): `/` returns 200 + renders Home component; latestPosts prop has up to 5 published (drafts excluded); homepage prop is null when no is_homepage=true Page exists; homepage prop is set when one does.
- `NotFoundTest` (2): unknown URL returns 404 + `Errors/NotFound` Inertia component; API-style request returns 404 JSON (or the default Laravel JSON, not the Inertia page).

**Steps:**
- [ ] 52.1 Create all files, modify routes + bootstrap
- [ ] 52.2 Regenerate wayfinder after routes update
- [ ] 52.3 Filter `--filter='HomeTest|NotFoundTest'` — 6 passes
- [ ] 52.4 types:check + pint + full suite → `197 passed, 0 failed` (191 + 6)
- [ ] 52.5 Commit: `feat(web): add homepage + 404 error page`

---

## Self-review

- Coverage: sitemap (51) + feed (51) + homepage (52) + 404 (52). ✓
- Reuses: Post published scope, PostResource camelCase output, PageResource, Inertia:render pattern.
- No new dependencies.

**Known risks:**
1. Laravel's default 404 might pre-empt our custom handler — test NotFoundTest verifies.
2. `Route::feeds()` is a spatie macro — might need to import it via ServiceProvider (usually auto-loaded by the package).
3. `welcome.tsx` currently at `resources/js/pages/welcome.tsx` — `/` route goes to it in the starter. Replacing with Home removes the starter's default; tests that previously asserted the welcome page (if any) need updating. The starter tests from Phase 0 don't test `/`, so no regression expected.
