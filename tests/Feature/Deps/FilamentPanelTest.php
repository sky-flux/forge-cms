<?php

declare(strict_types=1);

use App\Models\User;
use Spatie\Permission\Models\Role;

test('redirects guests from /admin to the filament login page', function (): void {
    $response = $this->get('/admin');

    $response->assertRedirect('/admin/login');
});

test('renders the filament dashboard for a super_admin user', function (): void {
    Role::create(['name' => 'super_admin']);
    $user = User::factory()->create();
    $user->assignRole('super_admin');

    $this->actingAs($user)->get('/admin')->assertSuccessful();
});

test('denies panel access to a regular user in production', function (): void {
    $this->app->detectEnvironment(fn () => 'production');

    $user = User::factory()->create();

    $this->actingAs($user)->get('/admin')->assertForbidden();
});
