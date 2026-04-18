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
