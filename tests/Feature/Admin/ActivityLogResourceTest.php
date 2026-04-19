<?php

declare(strict_types=1);

use App\Filament\Resources\ActivityLog\ActivityLogResource;
use App\Filament\Resources\ActivityLog\Pages\ListActivityLogs;
use App\Models\User;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    $this->withoutVite();
    Role::findOrCreate('super_admin');
});

test('ActivityLogResource binds to Spatie Activity under 系统', function (): void {
    expect(ActivityLogResource::getModel())->toBe(Activity::class)
        ->and(ActivityLogResource::getNavigationGroup())->toBe('系统')
        ->and(ActivityLogResource::getNavigationSort())->toBe(6);
});

test('super_admin can list activity log', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');

    Livewire::actingAs($admin)
        ->test(ListActivityLogs::class)
        ->assertSuccessful();
});

test('guests are redirected from activity log index', function (): void {
    $this->get(ActivityLogResource::getUrl('index'))->assertRedirect('/console/login');
});

test('resource disables create/edit/delete', function (): void {
    expect(ActivityLogResource::canCreate())->toBeFalse();
});
