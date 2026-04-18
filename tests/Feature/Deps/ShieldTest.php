<?php

declare(strict_types=1);

use App\Models\User;
use BezhanSalleh\FilamentShield\Support\Utils;
use Spatie\Permission\Models\Role;

test('registers the super_admin role name configured by shield', function (): void {
    expect(Utils::getSuperAdminName())->toBe('super_admin');
});

test('lets a super_admin user access the admin panel in production', function (): void {
    app()->detectEnvironment(fn () => 'production');

    $admin = User::factory()->create();
    Role::create(['name' => 'super_admin']);
    $admin->assignRole('super_admin');

    $this->actingAs($admin)->get('/admin')->assertSuccessful();
});
