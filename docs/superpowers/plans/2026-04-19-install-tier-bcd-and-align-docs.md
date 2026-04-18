# Install Tier B + C + D + Align Package Docs Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax.

**Goal:** Finish installing every package the PRD / `laravel.md` lists across Tier B (deferred by previous plan: sanctum / reverb / feed), Tier C (spatie convenience packages: sluggable / sitemap / activitylog / backup / honeypot), Tier D (dev tools: telescope / larastan / rector + rector-laravel), remove `laravel/sail` per `laravel.md §13.5`, and rewrite the two package tables (PRD §9, laravel.md §13) so they reflect reality instead of planning fiction.

**Architecture:** One package (or tightly coupled pair) per task = one commit. Each task follows the established 6-step TDD cycle from the previous plan (write failing test → confirm red → install/configure → confirm green → pint + full suite → commit). Documentation alignment runs after all packages land, so the "installed" status column in the docs matches what git actually shows.

**Tech Stack:** PHP 8.5 / Laravel 13 / Pest 4. Existing stack (from Tasks 1-12): Filament 5 / Shield / spatie-permission / spatie-medialibrary / Scout + Meilisearch / Octane + FrankenPHP / Horizon / ide-helper. Smoke tests live in `tests/Feature/Deps/`.

**Scope NOT included in this plan:** design-issue fixes (body_markdown dual storage, IP hash weakness, multi-language P0, story inconsistencies). Those go in a separate Phase 2 plan once packages are in.

---

## Pre-flight Checklist

- [ ] `git log --oneline | head -1` → current HEAD is `d059359` (Task 12 last commit).
- [ ] Full suite green: `env PATH="$HOME/.config/herd-lite/bin:$PATH" php artisan test` → `57 passed (156 assertions), 0 failed`.
- [ ] Working tree clean: `git status --short` prints nothing.
- [ ] Containers running: `docker ps | grep forge-cms` returns app/postgres/valkey/mailpit/meilisearch.
- [ ] Remote in sync: `git rev-list --count origin/main..main` → 0 (all commits pushed).

---

## File Structure Summary

**New test files (one per package group):**
- `tests/Feature/Deps/SluggableTest.php` / `SitemapTest.php` / `ActivityLogTest.php` / `BackupTest.php` / `HoneypotTest.php` / `FeedTest.php` / `SanctumTest.php` / `ReverbTest.php` / `TelescopeTest.php` / `LarastanTest.php` / `RectorTest.php`

**New config files (via vendor:publish):**
- `config/activitylog.php` / `config/backup.php` / `config/honeypot.php` / `config/feed.php` / `config/sanctum.php` / `config/reverb.php` / `config/telescope.php`

**New static analysis / refactor configs:**
- `phpstan.neon` (Larastan baseline)
- `rector.php` (Rector config)

**New migrations (published by installers):**
- `database/migrations/*_create_activity_log_table.php`
- `database/migrations/*_add_event_column_to_activity_log_table.php`
- `database/migrations/*_add_batch_uuid_column_to_activity_log_table.php`
- `database/migrations/*_create_personal_access_tokens_table.php`
- `database/migrations/*_create_telescope_entries_table.php`

**Model modifications:**
- `app/Models/User.php` — add `HasApiTokens` trait (Sanctum Task 19).

**Provider modifications:**
- `app/Providers/TelescopeServiceProvider.php` — created by installer; rewrite `gate()` to use `hasRole('super_admin')` (same pattern as Horizon fix).
- `bootstrap/providers.php` — installer-appended entries for Telescope.

**Env / config:**
- `.env.example` — `BROADCAST_CONNECTION` restored from `log` back to `reverb` (Task 20), `REVERB_*` keys verified present.
- `phpunit.xml` — `BROADCAST_CONNECTION=null` already set; no change needed.

**Docs rewrite:**
- `docs/prd.md` §9 tables — mark installed / dev / deferred status.
- `docs/laravel.md` §13 tables + §13.3 composer block — align with actual composer.json.

**Removed:**
- `laravel/sail` from `require-dev` (Task 24).

---

## Shared rules for every task below

1. **TDD cycle is non-negotiable.** Full suite (not just new test) must be 0 `failed` before commit. If any failed appears, report BLOCKED.
2. **No AI attribution.** No `Co-Authored-By: Claude`, no `🤖 Generated with Claude Code`.
3. **Test convention.** `test(...)` not `it(...)`, `declare(strict_types=1)`, NO redundant `uses(RefreshDatabase::class)` (Pest.php binds globally).
4. **Use make wrappers:** `make c` (composer), `make a` (host artisan). Flags that don't forward through make (`--filter=`, `--provider=`) go via direct `env PATH="$HOME/.config/herd-lite/bin:$PATH" php artisan …` invocation.
5. **Do NOT touch unrelated files.** Each task's "Files" list is authoritative.
6. **Test DB is SQLite `:memory:` per phpunit.xml.** If an installer migration is Postgres-only (unlikely but possible), adjust the migration or exclude from the test run and flag as concern.

---

## Task 13: spatie/laravel-sluggable

**Files:**
- Create: `tests/Feature/Deps/SluggableTest.php`

**Test:**
```php
<?php

declare(strict_types=1);

use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

test('exposes the HasSlug trait and SlugOptions builder', function (): void {
    expect(trait_exists(HasSlug::class))->toBeTrue();
    expect(class_exists(SlugOptions::class))->toBeTrue();
    expect(SlugOptions::create()->generateSlugsFrom('title')->saveSlugsTo('slug'))
        ->toBeInstanceOf(SlugOptions::class);
});
```

**Steps:**
- [ ] 13.1 Write test → FAIL (`Spatie\Sluggable\HasSlug` not found)
- [ ] 13.2 `make c require spatie/laravel-sluggable`
- [ ] 13.3 Rerun test → PASS
- [ ] 13.4 pint + full suite → `59 passed` (57 + 1 new asserting 3 things; or 58 — confirm with actual count)
- [ ] 13.5 Commit:
```
feat(deps): add spatie/laravel-sluggable for auto slug generation

Install spatie/laravel-sluggable so future Post / Page / Category /
Tag models can opt into auto-slug behaviour via the `HasSlug` trait
and the `SlugOptions` builder (generate from title, save to slug,
auto-dedupe on collision).

Smoke test asserts both the trait and the options builder are
class-loadable from the vendor autoload.
```

---

## Task 14: spatie/laravel-sitemap

**Files:**
- Create: `tests/Feature/Deps/SitemapTest.php`

**Test:**
```php
<?php

declare(strict_types=1);

use Spatie\Sitemap\Sitemap;
use Spatie\Sitemap\Tags\Url;

test('can build a sitemap with at least one url', function (): void {
    $sitemap = Sitemap::create()->add(Url::create('https://forge-cms.localhost/'));

    expect($sitemap->render())->toContain('<loc>https://forge-cms.localhost/</loc>');
});
```

**Steps:**
- [ ] 14.1 Write test → FAIL (class not found)
- [ ] 14.2 `make c require spatie/laravel-sitemap`
- [ ] 14.3 Rerun test → PASS
- [ ] 14.4 pint + full suite → green
- [ ] 14.5 Commit:
```
feat(deps): add spatie/laravel-sitemap for sitemap.xml generation

Install spatie/laravel-sitemap to power the `/sitemap.xml` route
required by PRD §3.1 (SEO 基础). The package provides `Sitemap`
and `Tags\Url` builders that serialize to the sitemap protocol XML.

Smoke test builds a one-URL sitemap and asserts the rendered XML
contains the expected `<loc>` element.
```

---

## Task 15: spatie/laravel-activitylog

**Files:**
- Create: `tests/Feature/Deps/ActivityLogTest.php`
- Created by publish: `config/activitylog.php`, `database/migrations/*_create_activity_log_table.php` (plus the two follow-up migrations for `event` and `batch_uuid` columns in v5)

**Test:**
```php
<?php

declare(strict_types=1);

use Spatie\Activitylog\Contracts\LogsActivity;
use Spatie\Activitylog\Models\Activity;

test('records an activity entry for a logged event', function (): void {
    activity()->log('test event');

    expect(Activity::query()->latest()->first()?->description)->toBe('test event');
});

test('exposes the LogsActivity contract for attaching to models', function (): void {
    expect(interface_exists(LogsActivity::class))->toBeTrue();
});
```

**Steps:**
- [ ] 15.1 Write test → FAIL
- [ ] 15.2 `make c require spatie/laravel-activitylog`
- [ ] 15.3 Publish migration + config:
  ```
  env PATH="$HOME/.config/herd-lite/bin:$PATH" php artisan vendor:publish --provider="Spatie\Activitylog\ActivitylogServiceProvider" --tag="activitylog-migrations"
  env PATH="$HOME/.config/herd-lite/bin:$PATH" php artisan vendor:publish --provider="Spatie\Activitylog\ActivitylogServiceProvider" --tag="activitylog-config"
  ```
- [ ] 15.4 Rerun test → PASS (activity() helper writes to the activity_log table)
- [ ] 15.5 pint + full suite → green
- [ ] 15.6 Commit:
```
feat(deps): add spatie/laravel-activitylog for audit trail

Install spatie/laravel-activitylog. Publish its migration (plus the
two v5 follow-up migrations that add `event` and `batch_uuid`
columns) and config.

Smoke test writes an entry with the `activity()` helper and asserts
it lands in the `activity_log` table, plus asserts the `LogsActivity`
contract is available for opt-in on future Post / Comment / User
models.
```

---

## Task 16: spatie/laravel-backup

**Files:**
- Create: `tests/Feature/Deps/BackupTest.php`
- Created by publish: `config/backup.php`

**Test:**
```php
<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;

test('registers the backup:run and backup:clean artisan commands', function (): void {
    expect(Artisan::all())
        ->toHaveKey('backup:run')
        ->toHaveKey('backup:clean')
        ->toHaveKey('backup:list');
});
```

**Steps:**
- [ ] 16.1 Write test → FAIL
- [ ] 16.2 `make c require spatie/laravel-backup`
- [ ] 16.3 Publish config:
  ```
  env PATH="$HOME/.config/herd-lite/bin:$PATH" php artisan vendor:publish --provider="Spatie\Backup\BackupServiceProvider"
  ```
- [ ] 16.4 Rerun test → PASS
- [ ] 16.5 pint + full suite → green
- [ ] 16.6 Commit:
```
feat(deps): add spatie/laravel-backup for db + storage backups

Install spatie/laravel-backup and publish `config/backup.php`. The
package registers three artisan commands (`backup:run` /
`backup:clean` / `backup:list`) that the scheduler (routes/console.php)
will wire up in a later task.

Smoke test asserts all three commands are in the Artisan registry.
Config defaults target `storage/app/{app-name}` locally; the
destination disk is switched per-env once a real S3/R2 bucket is
configured.
```

---

## Task 17: spatie/laravel-honeypot

**Files:**
- Create: `tests/Feature/Deps/HoneypotTest.php`
- Created by publish: `config/honeypot.php`

**Test:**
```php
<?php

declare(strict_types=1);

use Spatie\Honeypot\ProtectAgainstSpam;

test('exposes the honeypot middleware for route protection', function (): void {
    expect(class_exists(ProtectAgainstSpam::class))->toBeTrue();
    expect(config('honeypot.enabled'))->toBeTrue();
});
```

**Steps:**
- [ ] 17.1 Write test → FAIL
- [ ] 17.2 `make c require spatie/laravel-honeypot`
- [ ] 17.3 Publish config:
  ```
  env PATH="$HOME/.config/herd-lite/bin:$PATH" php artisan vendor:publish --provider="Spatie\Honeypot\HoneypotServiceProvider" --tag=config
  ```
- [ ] 17.4 Rerun test → PASS
- [ ] 17.5 pint + full suite → green
- [ ] 17.6 Commit:
```
feat(deps): add spatie/laravel-honeypot for form anti-spam

Install spatie/laravel-honeypot. Its `ProtectAgainstSpam` middleware
will gate the comment submission route (story.md US-070). Publish
`config/honeypot.php`.

Smoke test asserts the middleware class is loadable and the config
ships enabled by default.
```

---

## Task 18: spatie/laravel-feed

**Files:**
- Create: `tests/Feature/Deps/FeedTest.php`
- Created by publish: `config/feed.php`

**Test:**
```php
<?php

declare(strict_types=1);

use Spatie\Feed\FeedServiceProvider;

test('registers the feed service provider in the application', function (): void {
    expect(app()->getLoadedProviders())->toHaveKey(FeedServiceProvider::class);
});

test('ships the feed config with at least one feed entry', function (): void {
    expect(config('feed.feeds'))->toBeArray();
});
```

**Steps:**
- [ ] 18.1 Write test → FAIL (provider / config missing)
- [ ] 18.2 `make c require spatie/laravel-feed`
- [ ] 18.3 Publish config:
  ```
  env PATH="$HOME/.config/herd-lite/bin:$PATH" php artisan vendor:publish --provider="Spatie\Feed\FeedServiceProvider" --tag=feed-config
  ```
- [ ] 18.4 Rerun test → PASS
- [ ] 18.5 pint + full suite → green
- [ ] 18.6 Commit:
```
feat(deps): add spatie/laravel-feed for RSS / Atom output

Install spatie/laravel-feed. The package exposes `/feed.xml` routes
once a Feedable model is wired up (Post in Phase 2). Publish
`config/feed.php` so the feed definition is editable.

Smoke test asserts the service provider is registered and the
published `feed.feeds` config key is an array.
```

---

## Task 19: laravel/sanctum

**Files:**
- Create: `tests/Feature/Deps/SanctumTest.php`
- Modify: `app/Models/User.php` — add `HasApiTokens` trait (alphabetical in trait list).
- Created by installer: `config/sanctum.php`, `database/migrations/*_create_personal_access_tokens_table.php`.

**Test:**
```php
<?php

declare(strict_types=1);

use App\Models\User;
use Laravel\Sanctum\PersonalAccessToken;

test('user can mint a personal access token via HasApiTokens', function (): void {
    $user = User::factory()->create();

    $token = $user->createToken('test-device');

    expect($token->accessToken)->toBeInstanceOf(PersonalAccessToken::class)
        ->and($token->plainTextToken)->toBeString();
});
```

**Steps:**
- [ ] 19.1 Write test → FAIL (`createToken` missing on User)
- [ ] 19.2 `make c require laravel/sanctum`
- [ ] 19.3 Run installer: `env PATH="$HOME/.config/herd-lite/bin:$PATH" php artisan install:api --no-interaction` (Laravel 11+ replaces the old `sanctum:install`)
- [ ] 19.4 Edit User.php — add `use Laravel\Sanctum\HasApiTokens;` + insert `HasApiTokens` in the alphabetical trait list (between `HasFactory` and `HasRoles`).
- [ ] 19.5 Rerun test → PASS
- [ ] 19.6 pint + full suite → green
- [ ] 19.7 Commit:
```
feat(deps): add laravel/sanctum for API token authentication

Install laravel/sanctum and run `install:api` to publish
`config/sanctum.php`, generate the `personal_access_tokens`
migration, and register the API route file.

Add `HasApiTokens` to `App\Models\User` (alphabetical trait order
preserved) so the model can mint access tokens. Smoke test creates
a factory user, mints a named token, and asserts the returned object
is a `PersonalAccessToken` instance with a plain-text secret.

Opens the door to PRD v1.x public REST API (§9.1 originally deferred
this, now in place ahead of demand).
```

---

## Task 20: laravel/reverb

**Files:**
- Create: `tests/Feature/Deps/ReverbTest.php`
- Modify: `.env.example` — change `BROADCAST_CONNECTION=log` back to `BROADCAST_CONNECTION=reverb` (undoes the Phase-1 hardening from commit `65fd97a`; we can safely enable reverb now since it's installed).
- Created by installer: `config/reverb.php`, possibly an update to `config/broadcasting.php` to add the `reverb` connection.

**Test:**
```php
<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;

test('registers the reverb:start artisan command', function (): void {
    expect(Artisan::all())->toHaveKey('reverb:start');
});

test('configures a reverb connection in the broadcasting config', function (): void {
    expect(config('broadcasting.connections.reverb'))->toBeArray()
        ->and(config('broadcasting.connections.reverb.driver'))->toBe('reverb');
});
```

**Steps:**
- [ ] 20.1 Write test → FAIL
- [ ] 20.2 `make c require laravel/reverb`
- [ ] 20.3 Run installer: `env PATH="$HOME/.config/herd-lite/bin:$PATH" php artisan reverb:install --no-interaction`
- [ ] 20.4 Update `.env.example`: replace `BROADCAST_CONNECTION=log` with `BROADCAST_CONNECTION=reverb` (revert the guard from commit `65fd97a`). Also verify `REVERB_APP_ID`, `REVERB_APP_KEY`, `REVERB_APP_SECRET`, `REVERB_HOST`, `REVERB_PORT`, `REVERB_SCHEME` keys are present (installer usually adds them — if not, hand-add with placeholder values).
- [ ] 20.5 Rerun test → PASS
- [ ] 20.6 pint + full suite → green
- [ ] 20.7 Commit:
```
feat(deps): add laravel/reverb websocket broadcaster

Install laravel/reverb and run `reverb:install`. Reverb is Laravel's
first-party WebSocket server — a drop-in replacement for Pusher /
Soketi with zero external dependencies.

Restore `.env.example` `BROADCAST_CONNECTION=reverb` (the previous
commit `65fd97a` downgraded it to `log` because reverb was uninstalled;
now that the package is in place we can safely use it as the default
broadcaster on fresh clones).

Smoke test asserts the `reverb:start` artisan command is registered
and the `broadcasting.connections.reverb` config node is present
with `driver=reverb`.
```

---

## Task 21: laravel/telescope

**Files:**
- Create: `tests/Feature/Deps/TelescopeTest.php`
- Created by installer: `config/telescope.php`, `app/Providers/TelescopeServiceProvider.php`, `database/migrations/*_create_telescope_entries_table.php`, asset publish under `public/vendor/telescope/`.
- Modify: `app/Providers/TelescopeServiceProvider.php` — rewrite `gate()` to use `hasRole('super_admin')` (install default gates to hardcoded email allow-list, same footgun as Horizon).
- Modify: `bootstrap/providers.php` — installer appends `TelescopeServiceProvider`; only register when `APP_ENV=local` to avoid production boot.

**Test:**
```php
<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Role;

test('registers the /telescope dashboard route', function (): void {
    $routes = collect(Route::getRoutes())->map->uri()->values()->all();

    expect($routes)->toContain('telescope/{view?}');
});

test('viewTelescope gate allows super_admin users', function (): void {
    Role::create(['name' => 'super_admin']);
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');

    expect(Gate::forUser($admin)->allows('viewTelescope'))->toBeTrue();
});

test('viewTelescope gate denies non-super-admin users', function (): void {
    $user = User::factory()->create();

    expect(Gate::forUser($user)->allows('viewTelescope'))->toBeFalse();
});
```

**Steps:**
- [ ] 21.1 Write test → FAIL
- [ ] 21.2 `make c require laravel/telescope --dev` (Telescope is dev-only per project convention; keeps it out of prod bundle)
- [ ] 21.3 Run installer: `env PATH="$HOME/.config/herd-lite/bin:$PATH" php artisan telescope:install`
- [ ] 21.4 Rewrite `TelescopeServiceProvider::gate()`:
```php
/**
 * Register the Telescope gate.
 *
 * This gate determines who can access Telescope in non-local environments.
 */
protected function gate(): void
{
    Gate::define('viewTelescope', function ($user = null): bool {
        return $user !== null && $user->hasRole('super_admin');
    });
}
```
Drop the default `['taylor@laravel.com']` scaffold.
- [ ] 21.5 Add `declare(strict_types=1);` at the top of `TelescopeServiceProvider.php` if missing.
- [ ] 21.6 Rerun test → PASS
- [ ] 21.7 pint + full suite → green
- [ ] 21.8 Commit:
```
feat(deps): add laravel/telescope (dev) with super_admin gate

Install laravel/telescope as a dev dep and run `telescope:install`.
Installer scaffolds the dashboard at `/telescope`, a service
provider, and a migration for the `telescope_entries` table.

Rewrite the `viewTelescope` gate to align with `/admin` and
`/horizon`: only users with `hasRole('super_admin')` pass, never
the installer's hardcoded email allow-list.

Tests cover both ends of the gate plus route registration. Package
is in `require-dev` so production bundle (composer install --no-dev)
doesn't include it.
```

---

## Task 22: larastan/larastan

**Files:**
- Create: `tests/Feature/Deps/LarastanTest.php`
- Create: `phpstan.neon` (Larastan baseline at level 5 to start; tighten later)
- Modify: `.gitignore` — ignore `.phpstan.cache` if Larastan uses one

**Test:**
```php
<?php

declare(strict_types=1);

test('ships the phpstan.neon config and larastan extension is loadable', function (): void {
    expect(file_exists(base_path('phpstan.neon')))->toBeTrue();
    expect(class_exists(\Larastan\Larastan\Properties\ReflectionExtension::class)
        || class_exists(\NunoMaduro\Larastan\Properties\ReflectionExtension::class))
        ->toBeTrue();
});
```

(Note: Larastan's namespace moved from `NunoMaduro\Larastan\*` to `Larastan\Larastan\*` in v3. Test accepts either so it isn't brittle on minor upgrades.)

**`phpstan.neon` content:**
```neon
includes:
    - ./vendor/larastan/larastan/extension.neon

parameters:
    paths:
        - app
        - tests
    level: 5
    excludePaths:
        - app/Filament/Resources/*
        - app/Providers/Filament/*.php
    treatPhpDocTypesAsCertain: false
```

**Steps:**
- [ ] 22.1 Write test + `phpstan.neon` → test still FAILs (larastan/larastan not in vendor)
- [ ] 22.2 `make c require --dev larastan/larastan`
- [ ] 22.3 Rerun test → PASS
- [ ] 22.4 Run `vendor/bin/phpstan analyse --no-progress 2>&1 | tail -20` — must report `[OK] No errors` OR errors should be catalogued and excluded in `phpstan.neon` (add `ignoreErrors` entries, do NOT lower level). If more than 5 errors pop, start at `level: 3` instead of 5.
- [ ] 22.5 pint + full suite → green
- [ ] 22.6 Commit:
```
feat(deps): add larastan/larastan for static analysis

Install larastan/larastan as a dev dep and drop a `phpstan.neon`
config at the project root. Level 5 as the initial target — strict
enough to catch real bugs (unused imports, unreachable code, invalid
property access) without drowning on generics.

`paths` cover `app/` and `tests/`. `excludePaths` skip Filament
installer-scaffolded files that fail Laravel-specific generic
inference (we can revisit once resources stabilise).

Smoke test asserts both the config file exists and the Larastan
extension class is loadable under either the legacy
`NunoMaduro\Larastan` or the v3 `Larastan\Larastan` namespace so
the test survives minor-version upgrades.
```

---

## Task 23: rector/rector + driftingly/rector-laravel

**Files:**
- Create: `tests/Feature/Deps/RectorTest.php`
- Create: `rector.php` (project-root config)

**`rector.php` content:**
```php
<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Set\ValueObject\LevelSetList;
use Rector\Set\ValueObject\SetList;
use RectorLaravel\Set\LaravelLevelSetList;
use RectorLaravel\Set\LaravelSetList;

return RectorConfig::configure()
    ->withPaths([
        __DIR__.'/app',
        __DIR__.'/tests',
    ])
    ->withSkip([
        __DIR__.'/app/Filament/Resources',
        __DIR__.'/app/Providers/Filament',
    ])
    ->withPhpSets(php85: true)
    ->withSets([
        LevelSetList::UP_TO_PHP_85,
        SetList::CODE_QUALITY,
        SetList::DEAD_CODE,
        SetList::EARLY_RETURN,
        LaravelLevelSetList::UP_TO_LARAVEL_110,
        LaravelSetList::LARAVEL_CODE_QUALITY,
    ]);
```

**Test:**
```php
<?php

declare(strict_types=1);

test('ships the rector config and base classes load', function (): void {
    expect(file_exists(base_path('rector.php')))->toBeTrue();
    expect(class_exists(\Rector\Config\RectorConfig::class))->toBeTrue();
    expect(class_exists(\RectorLaravel\Set\LaravelSetList::class))->toBeTrue();
});
```

**Steps:**
- [ ] 23.1 Write test + `rector.php` → test still FAIL (rector/rector not in vendor)
- [ ] 23.2 `make c require --dev rector/rector driftingly/rector-laravel`
- [ ] 23.3 Rerun test → PASS
- [ ] 23.4 Run `vendor/bin/rector process --dry-run --no-progress-bar 2>&1 | tail -30`. If Rector suggests changes to existing code, either:
  - (a) accept and commit them (`vendor/bin/rector process`), but this MUST be a separate `refactor(rector):` commit after Task 23 lands, OR
  - (b) add surgical `withSkip` entries in `rector.php` to exempt the flagged files for now.
  
  Do NOT sneak refactors into Task 23's commit.
- [ ] 23.5 pint + full suite → green
- [ ] 23.6 Commit (config + test only, NO code refactors):
```
feat(deps): add rector/rector + driftingly/rector-laravel (dev)

Install Rector and the Laravel rule set as dev deps. Drop
`rector.php` covering `app/` and `tests/`, skipping Filament
scaffolded files.

Rule sets:
- PHP 8.5 language-level upgrades
- Code quality / dead code / early return
- Laravel up-to-11.0 compatibility + Laravel code quality

Smoke test asserts the config file exists and the base classes for
both Rector and the Laravel set list resolve.

Running `vendor/bin/rector process --dry-run` now is expected to
surface refactor suggestions against the current codebase; applying
them is deferred to a follow-up `refactor(rector):` commit so this
dependency commit stays free of code changes.
```

---

## Task 24: Remove laravel/sail

**Files:**
- Modify: `composer.json`, `composer.lock` (composer remove)
- No test file added — removing a dev dep doesn't warrant a persistent test.

**Steps:**
- [ ] 24.1 Confirm sail is in `require-dev`: `grep sail composer.json`
- [ ] 24.2 `make c remove laravel/sail`
- [ ] 24.3 Confirm removed: `grep sail composer.json` → no output
- [ ] 24.4 pint + full suite → still green (sail never touched app code)
- [ ] 24.5 Commit:
```
chore(deps): remove laravel/sail

laravel.md §13.5 explicitly recommends removing laravel/sail because
Colima + our compose.yml stack already covers every workflow Sail
provides (mysql/postgres services, container-exec aliases, artisan
shortcuts). Sail sitting in require-dev adds composer install time
and confuses new contributors who try `sail up` instead of `make dev`.

Remove it. No app-code impact — Sail only ships a bash binary and
docker-compose files we don't use.
```

---

## Task 25: Align PRD §9 package status

**File:** `docs/prd.md`

**Rewrite scope:** section 9 only (sub-headings 9.1 / 9.2 / 9.3 / 9.4 / 9.5). Do NOT touch sections 1-8, 10, 11, or headers outside §9.

**Goal:** every row in every table gets an explicit "installed", "dev", or "deferred (v1.x)" status marker. Remove the contradiction where sanctum / reverb sit under "必装" with a footnote saying "v1.x 会用 / 未来开放时启用" — either they're installed now (which after this plan they are) or they're not.

**New shape:**
```markdown
### 9.1 生产依赖(`require`)

| 包 | 版本 | 状态 | 用途 |
|----|------|------|------|
| `inertiajs/inertia-laravel` | ^3.0 | ✅ starter | Inertia 服务端 adapter |
| `laravel/framework` | ^13.5 | ✅ starter | 框架本体 |
| `laravel/fortify` | ^1.34 | ✅ starter | 认证后端 |
| `laravel/wayfinder` | ^0.1 | ✅ starter | 类型安全的 TS 路由函数 |
| `laravel/tinker` | ^3.0 | ✅ starter | artisan tinker REPL |
| `laravel/octane` | ^2.17 | ✅ installed | FrankenPHP worker runner |
| `laravel/horizon` | ^5.45 | ✅ installed | Redis 队列 dashboard |
| `laravel/scout` | ^11.1 | ✅ installed | 搜索抽象层 |
| `laravel/sanctum` | ^4.3 | ✅ installed | API tokens |
| `laravel/reverb` | ^1.10 | ✅ installed | WebSocket broadcaster |
| `meilisearch/meilisearch-php` | ^1.16 | ✅ installed | Scout 的 Meili 驱动 |
| `filament/filament` | ^5.5 | ✅ installed | 管理后台 |
| `bezhansalleh/filament-shield` | ^4.2 | ✅ installed | Filament + permission 桥 |
| `filament/spatie-laravel-media-library-plugin` | ^5.5 | ✅ installed | Filament media 上传组件 |
| `spatie/laravel-permission` | ^7.3 | ✅ installed | 角色与权限 |
| `spatie/laravel-medialibrary` | ^11.21 | ✅ installed | 媒体文件管理 |
| `spatie/laravel-sluggable` | ^3.8 | ✅ installed | 自动 slug |
| `spatie/laravel-sitemap` | ^8.1 | ✅ installed | sitemap.xml 生成 |
| `spatie/laravel-activitylog` | ^5.0 | ✅ installed | 审计日志 |
| `spatie/laravel-backup` | ^10.2 | ✅ installed | 定时备份 |
| `spatie/laravel-honeypot` | ^4.7 | ✅ installed | 评论 / 表单反垃圾 |
| `spatie/laravel-feed` | ^4.5 | ✅ installed | RSS / Atom feed |

### 9.2 开发依赖(`require-dev`)

| 包 | 版本 | 状态 | 用途 |
|----|------|------|------|
| `laravel/pint` | ^1.29 | ✅ starter | 代码格式化 |
| `laravel/pail` | ^1.2 | ✅ starter | 实时日志 tail |
| `laravel/boost` | ^2.4 | ✅ starter | Laravel Boost MCP |
| `laravel/mcp` | ^0.6 | ✅ starter | MCP 基础 |
| `pestphp/pest` | ^4.6 | ✅ starter | 测试框架 |
| `pestphp/pest-plugin-laravel` | ^4.1 | ✅ starter | Pest Laravel 扩展 |
| `fakerphp/faker` | ^1.24 | ✅ starter | 测试假数据 |
| `mockery/mockery` | ^1.6 | ✅ starter | Mock 对象 |
| `nunomaduro/collision` | ^8.9 | ✅ starter | 异常输出美化 |
| `barryvdh/laravel-ide-helper` | ^3.7 | ✅ installed | IDE 类型提示 |
| `laravel/telescope` | ^5.20 | ✅ installed | 请求 / 查询调试面板 |
| `larastan/larastan` | ^3.9 | ✅ installed | 静态分析 |
| `rector/rector` | ^2.4 | ✅ installed | 自动重构 |
| `driftingly/rector-laravel` | ^2.3 | ✅ installed | Rector 的 Laravel 规则集 |

> **已移除**:`laravel/sail`(Colima + compose.yml 已覆盖,见 laravel.md §13.5)

### 9.3 前端生产依赖(`package.json`)

(keep existing JSON; add a note at the top mentioning `zod` / `cmdk` / `date-fns` are **not** installed until a feature requires them — avoid front-end bloat)

### 9.4 v1.x 再加的

(keep existing content but remove any package row that is now actually installed above — e.g. the "feed" row if it exists here duplicates §9.1)

### 9.5 明确**不**用的

(append `laravel/sail` to the list with reason "Colima + compose.yml 已覆盖 Sail 所有场景")
```

**Steps:**
- [ ] 25.1 Read `docs/prd.md` §9 current content.
- [ ] 25.2 Rewrite §9.1 / §9.2 / §9.3 / §9.4 / §9.5 per the shape above. Preserve existing prose style, only update the tables and the explanatory sentences that reference specific package statuses.
- [ ] 25.3 Verify §7 "已锁定技术决策" table still lists Sanctum / Reverb correctly (they were marked "v1.x" — update the note to say "installed ahead of demand, ready to use").
- [ ] 25.4 pint is markdown-only here; still run `vendor/bin/pint --dirty --format agent` as a no-op sanity check.
- [ ] 25.5 Full suite → still green (docs don't affect tests).
- [ ] 25.6 Commit:
```
docs(prd): align §9 package tables with installed state

Rewrite PRD §9.1 / §9.2 / §9.5 to give every package row an explicit
install-status marker:

- ✅ starter — shipped by `laravel new --react --pest --bun`
- ✅ installed — landed via a feat(deps) commit in Phase 1 or Phase 2
- (removed) — was previously recommended, now gone from composer.json

Resolve the internal contradiction where `laravel/sanctum` sat under
"必装" with a "v1.x 会用" footnote: it's now actually installed, so
drop the footnote and flip the status to ✅.

Add `laravel/sail` to §9.5 "明确不用的" with the Colima / compose
rationale.

Also update §7 technical-decisions table to remove the "v1.x 会用"
tag from Sanctum and Reverb rows — they're wired up now.
```

---

## Task 26: Align laravel.md §13 package tables + composer block

**File:** `docs/laravel.md`

**Rewrite scope:** §13 only (subsections 13.1 / 13.2 / 13.3 / 13.4 / 13.5). Plus §14.4 if it still mentions Boost-install-workflow in a way that's stale now.

**Goal:**
1. §13.1 / §13.2 tables get the same ✅ status column as prd.md §9.
2. §13.3 "可直接粘贴的 composer.json require 块" — regenerate to match the ACTUAL `composer.json` state (copy the current state of the file; no aspirational `require` entries). Drop `laravel/sail`.
3. §13.3 "安装顺序建议" bash block — update the `composer require` line-list to reflect what was actually run in Tasks 1-24. Order-sensitive; match the chronology of the feat(deps) commits.
4. §13.4 "关于 Packagist 头部流行包" — no change unless a reference is stale.
5. §13.5 "明确不用的包" — ensure `laravel/sail` is listed. Remove `tightenco/ziggy` if already listed twice. Verify the "替代" column for each row is accurate (wayfinder replaces ziggy etc.).
6. PHP constraint column in §13.1 — double-check `^8.2` / `^8.3` / `^8.2|^8.3` notation. `^8.2` already includes 8.3+, so `^8.2|^8.3` is redundant; simplify to `^8.2`.

**Steps:**
- [ ] 26.1 Read `docs/laravel.md` §13 current content.
- [ ] 26.2 Rewrite §13.1 table: add `状态` column, mark each row ✅ starter / ✅ installed. Fix PHP constraint notation. Remove any package row now actually gone (laravel/sail).
- [ ] 26.3 Rewrite §13.2 table similarly.
- [ ] 26.4 Regenerate §13.3 "可直接粘贴 composer.json require 块" from the actual `composer.json` file. Verify with `diff <(jq '.require, ."require-dev"' composer.json) <(the block in docs)` — content must match byte-for-byte (ignoring formatting).
- [ ] 26.5 Update §13.3 "安装顺序建议" bash block: match the actual sequence `make c require …` commands from Tasks 1-24 git log.
- [ ] 26.6 Update §13.5: append `laravel/sail` entry, verify others are still accurate.
- [ ] 26.7 pint (markdown no-op) + full suite → green.
- [ ] 26.8 Commit:
```
docs(laravel): align §13 package tables + composer block with reality

Rewrite laravel.md §13 so the tables and the "可直接粘贴"
composer.json block reflect what git + composer.json currently say:

- §13.1 / §13.2 gain a `状态` column: ✅ starter / ✅ installed.
- §13.3 composer.json block regenerated from the live file — drop
  `laravel/sail`, sanity-check version constraints.
- §13.3 bash install sequence matches the real order feat(deps)
  commits landed in Phase 1-2.
- §13.5 "不用的" list appends `laravel/sail` with the Colima rationale.
- Simplify redundant `^8.2|^8.3` PHP constraints to `^8.2` (the
  caret range already includes 8.3+).

Together with `docs(prd)` §9 alignment, the two authoritative
package tables now agree with each other and with composer.json.
```

---

## Self-review checklist (planner runs before handoff)

**1. Spec coverage:**
- Tier B packages — sanctum (Task 19), reverb (Task 20), feed (Task 18) ✓
- Tier C packages — sluggable (13), sitemap (14), activitylog (15), backup (16), honeypot (17) ✓
- Tier D packages — telescope (21), larastan (22), rector + rector-laravel (23) ✓
- Sail removal — Task 24 ✓
- PRD doc alignment — Task 25 ✓
- laravel.md doc alignment — Task 26 ✓

**2. Placeholder scan:** every test block has real PHP, every commit message has real content, every bash command has real flags. No TBDs.

**3. Type consistency:**
- `HasApiTokens` (Task 19), `HasRoles` (Task 1), `InteractsWithMedia` (Task 4) — all Spatie / Sanctum canonical names, alphabetical in trait list.
- `viewTelescope` (Task 21) gate mirrors `viewHorizon` (Task 7) exactly — same `$user !== null && $user->hasRole('super_admin')` shape.
- `Route::has('horizon.index')` pattern (Task 7 fix) reused implicitly via URI pattern in `Route::getRoutes()->map->uri()` for Task 21 — consistent.

**Known risks:**
1. **Rector `--dry-run` may find dozens of refactor candidates** in the current codebase, especially PHP 8.5 upgrades (`readonly`, constructor promotion, `match`) that the starter didn't apply. Task 23 defers these to a follow-up `refactor(rector):` commit — don't accidentally bloat the Rector dep commit with code diffs.
2. **Telescope storage migrations target SQLite fine** for tests, but the `telescope:install` generated migration uses `->longText('content')` which may be memory-heavy in `:memory:` — if tests become slow, add a guard to skip the migration in testing env.
3. **Reverb restoration of `.env.example BROADCAST_CONNECTION=reverb`** partially reverts Phase-1 commit `65fd97a`. Not a bug — the original guard only made sense while reverb was uninstalled. Commit message in Task 20 explains.
4. **Larastan + Rector at level 5 with real code** may both flag the Filament scaffolded files (RolePolicy, AdminPanelProvider, TelescopeServiceProvider). Both tasks exclude those paths; verify exclusions land before analyse / process runs.

---

## Execution handoff

**Plan complete and saved to `docs/superpowers/plans/2026-04-19-install-tier-bcd-and-align-docs.md`.**

Same execution mode as the previous MVP deps plan: **Subagent-Driven Development** — one subagent per task, two-stage review (spec + code quality) between tasks, main session coordinates and applies fix commits when reviewers find issues.

Tasks 13-26 = 14 new commits expected, plus 0-4 review-fix commits depending on what the reviewers find. Target final state: ~34 commits on main, full suite 70+ passed (57 existing + 13+ new smoke tests; activity-log and sanctum tests each add 2+ assertions).
