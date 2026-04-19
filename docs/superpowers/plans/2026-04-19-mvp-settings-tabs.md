# MVP Settings Tabs — Implementation Plan (Batch 4)

> Sequential tasks on `feat/mvp-settings-tabs` worktree. Linear merge to main via `git rebase main` + `git merge --ff-only`. 1 task = 1 commit. Implementer never commits.

**Stack:** Filament 5.5.2, spatie/laravel-settings 3.7, Pest 4.
**Scope:** Turn `系统 → 配置` (one Filament Page) into a **10-Tab** panel covering all site-wide config. `BackupSettings` class already scaffolded in `cbf7bba`.

## Conventions (strict — every task)

- `declare(strict_types=1);` every PHP file
- Pest `test()` + `$this->actingAs(...)` + `->assertInertia(...)` / `Livewire::test(...)` — NO `Pest\Laravel\...` functional imports
- For each new Settings class: `public typed props` + `group(): string` + settings-migration with `up()`/`down()`
- Register each Settings class in `config/settings.php` `settings` array
- `withoutVite()` in `beforeEach` for any Livewire/Inertia tests
- Run: `php artisan test --compact --filter=...`
- `vendor/bin/pint --dirty --format agent` after each task

## Architecture

- One `App\Filament\Pages\SystemSettings` Page (exists)
- `form()` returns a `Schema` with a top-level `Tabs` component wrapping 10 `Tab` children
- Each Tab binds its fields under a statePath matching the Settings group (e.g. `general.*` / `backup.*` / `comments.*`)
- `mount()` populates `$this->data` from all registered Settings in parallel:
  ```php
  $this->data = [
      'general' => app(GeneralSettings::class)->toArray(),
      'backup'  => app(BackupSettings::class)->toArray(),
      'comments' => app(CommentSettings::class)->toArray(),
      // ...
  ];
  ```
- `save()` iterates each group, fills its Settings instance, persists

## Task breakdown (7 tasks total)

### T1 — Refactor SystemSettings to Tabs + integrate 备份 Tab

- Rewrite `SystemSettings::form()` into Tabs layout
- First two Tabs wired: **基本信息** (existing GeneralSettings) + **备份** (existing BackupSettings from `cbf7bba`)
- Rewrite `mount()` and `save()` to handle multi-group state
- Update existing `SystemSettingsPageTest` — all existing assertions must still pass
- Add new tests: the 备份 Tab renders, saving persists BackupSettings values
- Also wire the `withSchedule()` block in `bootstrap/app.php` that reads BackupSettings + calls `backup:clean` + `backup:run` (guarded by `enabled` flag)

### T2 — 评论策略 Tab (`CommentSettings`)

Fields:
- `default_status: CommentStatus` (Pending|Approved|Trash) — default `Pending`
- `allow_guests: bool` — default `true`
- `max_depth: int` — default `3`, min `1`, max `5`
- `throttle_per_minute: int` — default `3`, min `1`, max `60`
- `notify_author_on_reply: bool` — default `false`
- `honeypot_enabled: bool` — default `true`

Must **actually wire** into the runtime:
- `CommentObserver::created()` currently notifies super_admin on `Pending`; change the `status !== Pending` gate to honor `$settings->default_status` if the comment arrived with no explicit status
- `CommentController` depth guard: replace hardcoded `MAX_DEPTH = 3` with `app(CommentSettings::class)->max_depth`
- Tests: saving settings flips behavior

### T3 — SEO 配置 Tab (`SeoSettings`)

Fields:
- `google_analytics_id: ?string`
- `google_tag_manager_id: ?string`
- `google_site_verification: ?string`
- `bing_site_verification: ?string`
- `twitter_site_handle: ?string`
- `facebook_app_id: ?string`
- `robots_extra: ?string` — extra lines appended to standard robots.txt
- `sitemap_include_categories: bool` — default true
- `sitemap_include_tags: bool` — default true

Must wire:
- `SitemapController` reads the two `sitemap_include_*` flags
- If any of GA/GTM/verify codes set, render them as `<meta>` / `<script>` in public Inertia layout (extend HandleInertiaRequests middleware shared props)

Tests: include toggles honored by sitemap; GA id exposed in shared props when set

### T4 — 媒体上传 Tab (`MediaUploadSettings`) + RSS/Sitemap Tab (`FeedSettings`)

`MediaUploadSettings`:
- `max_upload_size_mb: int` — default `10`
- `allowed_mime_types_csv: string` — default `image/jpeg,image/png,image/gif,image/webp,application/pdf`
- `auto_convert_to_webp: bool` — default `false`
- `image_quality: int` — default `85`, min `1`, max `100`

`FeedSettings`:
- `items_per_feed: int` — default `50`
- `feed_cache_ttl_minutes: int` — default `60`
- `include_excerpts_in_feed: bool` — default `true`
- `sitemap_default_priority: float` — default `0.5`
- `sitemap_change_frequency: string` — default `weekly`

Must wire:
- `Post::getFeedItems()` reads `items_per_feed`
- Response-caching on feed + sitemap respects `feed_cache_ttl_minutes`
- MediaUpload settings read by new upload middleware/validator (may skip middleware wiring in MVP — just persist + expose for future use; document)

### T5 — 邮件 Tab (`MailSettings`) + 外观 Tab (`AppearanceSettings`)

`MailSettings`:
- `from_name: ?string`
- `from_address: ?string`
- `reply_to: ?string`
- `footer_template: ?string` — markdown

`AppearanceSettings`:
- `logo_url: ?string`
- `favicon_url: ?string`
- `primary_color: string` — default `#1e40af` (tailwind blue-800)
- `footer_text: ?string`

Wire:
- If `from_name`/`from_address` set, override `config('mail.from.*')` at runtime (inside a request-bound service provider, Octane-safe via `Mail::alwaysFrom(...)` once per request) — or simpler: document that these values are used BY our Notification classes via accessor (skip runtime config mutation)
- Frontend Inertia shared props expose `appearance.logo_url` etc. so public pages can render dynamic logo/footer

### T6 — 性能与缓存 Tab (`PerformanceSettings`) + 法律/Cookie Tab (`LegalSettings`)

`PerformanceSettings`:
- `post_cache_ttl_minutes: int` — default `60`
- `sitemap_cache_ttl_hours: int` — default `24`
- `scout_batch_size: int` — default `500`

`LegalSettings`:
- `terms_url: ?string`
- `privacy_url: ?string`
- `cookie_banner_enabled: bool` — default `false`
- `cookie_banner_text: ?string`
- `gdpr_comment_opt_in: bool` — default `false`

Wire: frontend shared props render cookie banner if enabled; footer shows terms/privacy links if set.

### T7 — 安全策略 Tab (`SecuritySettings`)

Fields:
- `require_2fa_for_super_admin: bool` — default `false`
- `session_lifetime_minutes: int` — default `120`
- `password_min_length: int` — default `12`
- `max_login_attempts: int` — default `5`
- `lockout_minutes: int` — default `15`

Wire:
- Override `config('session.lifetime')` inside `web` middleware via a `ConfigFromSettings` middleware (Octane-safe: request-scoped)
- Fortify login attempts throttle — document linkage; actual enforcement probably stays in Fortify config for MVP; just expose the knobs

## Merge strategy

After each task is committed on `feat/mvp-settings-tabs`:
```bash
cd .worktrees/mvp-settings-tabs
git rebase main          # no-op if main hasn't moved
cd /main-workspace
git merge --ff-only feat/mvp-settings-tabs
```

OR accumulate all 7 commits on the branch then merge once at the end via ff-only (main hasn't moved during Batch 4 since no other worktree is active).

## Acceptance (batch complete)

- `系统 → 配置` at `/console/settings` has 10 Tabs
- Each Tab saves and reloads its group
- Schedule runs backup daily when enabled
- Comment depth limit is user-configurable, not hardcoded
- Sitemap honors `sitemap_include_*` toggles
- RSS honors `items_per_feed`
- Shared Inertia props expose logo/favicon/analytics ids for frontend
- 10+ new tests green, full suite no regressions
