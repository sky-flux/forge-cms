<?php

declare(strict_types=1);

use App\Filament\Pages\Cache as CachePage;
use App\Models\User;
use Filament\Actions\Action;
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
        protected function opcacheStatus(): ?array
        {
            return null;
        }
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
        ->assertActionExists('flushApp', fn (Action $action): bool => $action->isConfirmationRequired());
});

test('resetOpcache header action requires confirmation', function (): void {
    if (! function_exists('opcache_reset')) {
        $this->markTestSkipped('opcache extension not loaded');
    }

    $admin = User::factory()->create();
    $admin->assignRole('super_admin');

    Livewire::actingAs($admin)
        ->test(CachePage::class)
        ->assertActionExists('resetOpcache', fn (Action $action): bool => $action->isConfirmationRequired());
});
