# 系统 → 缓存 Page — Implementation Plan

> Spec: `docs/superpowers/specs/2026-04-19-rich-cache-page.md`
> Linear main via `git rebase main` + `git merge --ff-only`. 1 task = 1 commit.

**Worktree:** `.worktrees/rich-cache-page` on `feat/rich-cache-page`.
**Stack:** Laravel 13, Filament 5.5.2, Pest 4, spatie/laravel-activitylog (already installed).

## File plan

| File | Action | Responsibility |
|---|---|---|
| `app/Filament/Pages/Cache.php` | Modify | `getHeaderActions()` + stats methods + activity logging |
| `resources/views/filament/pages/cache.blade.php` | Rewrite | Stats grid + warning banner + recent actions table |
| `tests/Feature/Admin/CachePageTest.php` | Extend | Add activity-log + stats + Opcache-absent tests |

## One task, one commit (user's "1 task = 1 commit" memory rule allows grouping logically cohesive work)

### Task — Rich Cache Page

#### TDD

**Step 1 — failing tests.** Replace/extend `tests/Feature/Admin/CachePageTest.php`:

```php
<?php

declare(strict_types=1);

use App\Filament\Pages\Cache as CachePage;
use App\Models\User;
use Filament\Actions\Testing\Fixtures\TestAction;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    $this->withoutVite();
    Role::findOrCreate('super_admin');
    Role::findOrCreate('admin');
});

test('Cache page lives under 系统 with sort 9', function (): void {
    expect(CachePage::getNavigationGroup())->toBe('系统')
        ->and(CachePage::getNavigationSort())->toBe(9);
});

test('super_admin can flush app cache and the action is logged', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');

    Cache::put('foo', 'bar', 60);

    Livewire::actingAs($admin)
        ->test(CachePage::class)
        ->call('flushApp');

    expect(Cache::get('foo'))->toBeNull()
        ->and(Activity::where('log_name', 'cache')->where('event', 'cache:flush')->exists())->toBeTrue();
});

test('clearEvent invokes event:clear and logs', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');

    Livewire::actingAs($admin)
        ->test(CachePage::class)
        ->call('clearEvent');

    expect(Activity::where('log_name', 'cache')->where('event', 'event:clear')->exists())->toBeTrue();
});

test('getRecentActionsStats returns total + latest', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');

    $this->actingAs($admin);

    activity('cache')->event('config:clear')->log('test');
    activity('cache')->event('view:clear')->log('test');

    $page = new CachePage;
    $stats = $page->getRecentActionsStats();

    expect($stats['total'])->toBe(2)
        ->and($stats['last_at'])->not->toBeNull();
});

test('getCacheBackendStats returns driver name and handles non-redis gracefully', function (): void {
    // Test env uses array driver per phpunit.xml
    $page = new CachePage;
    $stats = $page->getCacheBackendStats();

    expect($stats['driver'])->toBe('array');
});

test('opcache stats respect probe override', function (): void {
    // Subclass the Page to override the probe so we can exercise the disabled branch
    $page = new class extends CachePage
    {
        protected function opcacheStatus(): ?array { return null; }
    };

    $stats = $page->getOpcacheStats();
    expect($stats['enabled'])->toBeFalse();
});

test('non-super_admin user is forbidden from cache page', function (): void {
    $editor = User::factory()->create();
    $editor->assignRole('admin');

    $this->actingAs($editor)->get(CachePage::getUrl())->assertForbidden();
});

test('guest redirected from cache page', function (): void {
    $this->get(CachePage::getUrl())->assertRedirect('/console/login');
});

test('flushApp header action requires confirmation', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');

    Livewire::actingAs($admin)
        ->test(CachePage::class)
        ->assertActionExists('flushApp', fn (TestAction $action): TestAction => $action->requiresConfirmation());
});

test('resetOpcache header action requires confirmation', function (): void {
    if (! function_exists('opcache_reset')) {
        $this->markTestSkipped('opcache extension not loaded');
    }

    $admin = User::factory()->create();
    $admin->assignRole('super_admin');

    Livewire::actingAs($admin)
        ->test(CachePage::class)
        ->assertActionExists('resetOpcache', fn (TestAction $action): TestAction => $action->requiresConfirmation());
});
```

**Note**: If `Filament\Actions\Testing\Fixtures\TestAction` doesn't exist in Filament 5.5.2, fall back to inline assertions via `->assertActionVisible('flushApp')` + direct property reads.

**Step 2.** Run — confirm RED (missing: `getHeaderActions`, `clearEvent`, `resetOpcache`, `getRecentActionsStats`, `getCacheBackendStats`, `getOpcacheStats`, activity logging).

**Step 3 — rewrite `app/Filament/Pages/Cache.php`:**

Replace the class to:

- Keep existing `navigationIcon / Group / Sort / Label / Title / Slug / view` + `canAccess()` + `mount()` guard
- Public methods `clearConfig / clearRoute / clearView / clearEvent / flushApp / resetOpcache` — each uses `private recordAction(string $event, string $title)` helper
- `recordAction`: sets `$lastClearedAt`, calls `activity('cache')->causedBy(auth()->user())->event($event)->log($title)`, emits success Notification
- `getHeaderActions(): array` — 6 `Action` entries with icons + colors:
  - `flushApp` → `requiresConfirmation() + modalHeading('确认清空应用缓存?') + modalDescription('...')` (danger color)
  - **`resetOpcache` → `requiresConfirmation() + modalHeading('确认重置 Opcache?') + modalDescription('当前 worker 的字节码将失效,下一次请求会触发全部脚本重编译,高 QPS 下可能延迟尖峰')`** (warning color, review #6)
  - `resetOpcache` uses `->visible(fn () => function_exists('opcache_reset'))`
- `getCacheBackendStats(): array` — handles BOTH phpredis flat AND predis nested shapes (review #2). Code in spec § "缓存后端". Falls back to `['driver' => $driver]` when not redis, `['driver' => 'redis', 'connected' => false, 'error' => ...]` on throw
- `getOpcacheStats(): array` — **delegates to `protected function opcacheStatus(): ?array`** that returns raw `opcache_get_status(false)` or null (review #4). Tests override this in anonymous subclass
- `getRecentActions(): array` uses `->latest('id')` NOT `->latest()` (review #5 — log_name has only single-col index, so ORDER BY created_at would filesort)
- `getRecentActionsStats(): array` — total count + last_at
- Heroicon imports: `OutlinedCog6Tooth`, `OutlinedMap`, `OutlinedEye`, `OutlinedBolt`, `OutlinedCpuChip`, `OutlinedTrash`

**Step 4 — rewrite `resources/views/filament/pages/cache.blade.php`:**

Use `<x-filament-panels::page>` wrapper. Inside:

1. **Stats grid**: `<div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">` with 4 cards.
   - Each card wrapped with `<x-filament::section>` (review #3 — don't paste bare `fi-section` class, use the Blade component)
   - Stat title: small gray text; value: `text-2xl font-semibold`; meta: `text-xs text-gray-500`
2. **Warning banner**: amber panel. Link to activity log page via **`\App\Filament\Resources\ActivityLog\ActivityLogResource::getUrl('index')`** (review #1 — route name fix; don't hand-spell `route('filament.admin.resources.activities.index')` which doesn't exist).
3. **Recent actions table**: standard table using Tailwind utilities (`text-sm border divide-y border-gray-100 dark:border-white/10`). Causer column uses `{{ $row['causer'] ?? '系统' }}` — blade null-safe chain already in place via the array shape from `getRecentActions()` (review #8).

**Step 5 — run tests, GREEN.**

**Step 6 — Pint.**

## CR focus

- Stats methods degrade gracefully on failure (no 500 if Redis offline)
- Opcache action button uses `->visible(fn () => function_exists('opcache_reset'))` — hidden, not errored
- `flushApp` confirmation has Chinese copy
- Activity log `log_name='cache'` matches existing Activity conventions
- No new migration needed (activity_log table exists)
- Octane: stats resolved per-request, no static accumulation

## Acceptance

- Page visually coherent with Filament look (no CSS-less text fallback — review #3 cleared)
- 4 stat cards render on first load; Redis offline / Opcache missing → 友好降级文案 instead of 500
- 6 header actions appear; `flushApp` + `resetOpcache` 都有 modal 二次确认(review #6)
- Every action writes to `activity_log` with `log_name='cache'`; recent-10 table uses `latest('id')` 避免 filesort(review #5)
- Activity link goes to correct route `ActivityLogResource::getUrl('index')` (review #1)
- Opcache probe 抽成 `protected opcacheStatus()`,测试可通过匿名子类 override(review #4)
- 10 tests green (原 3 + 新 7);includes non-super_admin 403 + action-confirmation assertions(review #9)
- Linear commit on main via `git rebase main && git merge --ff-only`

## Risks / mitigations

| Risk | Mitigation |
|---|---|
| Redis `info()` schema differs between redis-cli versions | Handles both `Memory.used_memory_human` and flat `used_memory_human`; same for `Keyspace` |
| Activity link route name wrong | Step 4 confirms with `route:list --name=activities`; fallback to `url('/console/activities')` |
| Filament's modal heading UTF-8 truncation | Tested via Livewire `callMountedAction` — should handle Chinese fine; if issues, shorten heading |
| Test env has no Redis | `getCacheBackendStats` returns `['driver' => 'array']` in test (phpunit.xml sets `CACHE_STORE=array`) — already handled |
| phpredis vs predis info() shape | Implementation iterates `$info` and detects both `db0` root-level strings (phpredis) and `Keyspace.db0.keys` nested (predis) — review #2 |
| CI/test env has opcache loaded → disabled branch untestable | Probe abstracted to `protected opcacheStatus()` — anonymous subclass overrides to simulate disabled (review #4) |
| Causer soft-deleted → null ref in blade | `getRecentActions()` returns `['causer' => $a->causer?->name ?? null]`; blade uses `{{ $row['causer'] ?? '系统' }}` (review #8) |
