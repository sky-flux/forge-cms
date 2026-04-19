<?php

declare(strict_types=1);

use App\Filament\Pages\Cache as CachePage;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    $this->withoutVite();
    Role::findOrCreate('super_admin');
});

test('Cache page lives under 系统', function (): void {
    expect(CachePage::getNavigationGroup())->toBe('系统')
        ->and(CachePage::getNavigationSort())->toBe(9);
});

test('super_admin can flush app cache', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');

    Cache::put('foo', 'bar', 60);
    expect(Cache::get('foo'))->toBe('bar');

    Livewire::actingAs($admin)
        ->test(CachePage::class)
        ->call('flushApp');

    expect(Cache::get('foo'))->toBeNull();
});

test('guest redirected from cache page', function (): void {
    $this->get(CachePage::getUrl())->assertRedirect('/console/login');
});
