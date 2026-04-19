# MVP Completion — Batch 1 Spec

**Date:** 2026-04-19
**Scope:** Finish 3 P1 stories (US-042 Search, US-043 Feed/Sitemap, US-013 Scheduled publishing) + light SEO polish. Split across 2 parallel worktrees.

## Worktree A — `feat/mvp-public-essentials` (4 tasks, sequential)

Touches `routes/web.php` + web controllers. Sequential tasks.

### Task 1 — Search (US-042)

- Add `Searchable` trait + `toSearchableArray()` + `shouldBeSearchable()` to `Page` model (match `Post` style).
- `App\Http\Controllers\Web\SearchController::index(Request $request): Response` — reads `q`, returns Inertia::render('Search', [...]) with merged post+page results.
- `resources/js/pages/Search.tsx` — search input + results grid.
- Route: `Route::get('/search', [SearchController::class, 'index'])->name('search');`
- Config: `SCOUT_QUEUE=true` default (config/scout.php) so Redis-backed index updates don't block requests.
- Tests: `tests/Feature/Web/SearchTest.php` — asserts empty query returns empty results, query=slug returns that post, XSS-escapes output, guests can search.

### Task 2 — RSS Feed (US-043)

- Populate `config/feed.php`: `items => App\Models\Post@getFeedItems`, `url => '/posts'`, title/description from GeneralSettings fallback. Ensure `Post` has `toFeedItem()` method returning `FeedItem` (spatie/laravel-feed's abstraction).
- Register the feed route: `Spatie\Feed\FeedServiceProvider` adds a `\Spatie\Feed\Http\FeedController` — wire via `Route::feeds()` or explicit route to `/feed.xml`.
- Test: `tests/Feature/Web/FeedTest.php` — GET `/feed.xml` returns 200, Content-Type XML, contains recent published Post titles.

### Task 3 — Sitemap (US-043)

- `App\Http\Controllers\Web\SitemapController::index` generates sitemap dynamically using `spatie/laravel-sitemap`, including: home, post index, every published post, every page, every category and tag slug.
- Route: `Route::get('/sitemap.xml', [SitemapController::class, 'index']);`
- Test: `tests/Feature/Web/SitemapTest.php` — GET `/sitemap.xml` returns XML with `<loc>` for home + sample post + sample page.

### Task 4 — SEO polish (OG image + canonical)

- Add `og:image` + `<link rel="canonical">` to every page's Inertia `<Head>`. Default og:image from GeneralSettings::default_og_image, override per-post if featured image exists.
- Update: `resources/js/pages/Posts/Show.tsx`, `Pages/Show.tsx`, `Home.tsx`, `Categories/Show.tsx`, `Tags/Show.tsx`, `Search.tsx`.
- Test: `tests/Feature/Web/SeoMetaTest.php` — one Inertia-assert test per route template asserting the meta props are present and have correct values.

## Worktree B — `feat/mvp-scheduled-publisher` (1 task, independent)

### Task 1 — Scheduled post publisher (US-013)

- Command: `App\Console\Commands\PublishScheduledPosts` — `protected $signature = 'posts:publish-scheduled'`. Finds `Post::where('status', PostStatus::Scheduled)->where('published_at', '<=', now())`, updates status to `Published`. Logs activity.
- Register in `bootstrap/app.php`:
  ```php
  ->withSchedule(function (Illuminate\Console\Scheduling\Schedule $schedule): void {
      $schedule->command('posts:publish-scheduled')->everyMinute()->withoutOverlapping();
  })
  ```
- Test: `tests/Feature/Console/PublishScheduledPostsTest.php` — (a) scheduled post with past `published_at` flips to Published; (b) scheduled post with future `published_at` stays Scheduled; (c) Draft status stays Draft regardless.

## Workflow (per task)

TDD → Pint → CR subagent → fix loop → controller commits. 1 task = 1 commit. Implementer subagents do NOT commit.

## Files that must NOT be modified (out of scope)

- AdminPanelProvider (unless strictly needed)
- Filament admin resources (not this batch)
- Any settings page
- No migrations unless adding an index justified by search/sitemap perf

## Acceptance (merged to main)

- `/search?q=...` returns results; both Post and Page searchable
- `/feed.xml` returns valid RSS XML with published posts
- `/sitemap.xml` returns valid sitemap
- OG image + canonical rendered on all public pages
- `php artisan posts:publish-scheduled` moves scheduled posts to published when due
- `254+ tests pass` (whatever new tests add, all still green)
