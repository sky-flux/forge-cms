<?php

declare(strict_types=1);

use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    Role::findOrCreate('super_admin');
    Role::findOrCreate('admin');
});

test('UserResource binds to the User model and lives under 系统', function (): void {
    expect(UserResource::getModel())->toBe(User::class)
        ->and(UserResource::getNavigationGroup())->toBe('系统')
        ->and(UserResource::getNavigationSort())->toBe(1);
});

test('super_admin can access the users index page', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');

    $this->actingAs($admin)
        ->get('/admin/users')
        ->assertSuccessful();
});

test('guests are redirected from /admin/users to login', function (): void {
    $this->get('/admin/users')->assertRedirect('/admin/login');
});
