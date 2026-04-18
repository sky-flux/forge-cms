<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Role;

test('registers the horizon artisan command', function (): void {
    expect(Artisan::all())->toHaveKey('horizon');
});

test('registers the horizon dashboard route at /horizon', function (): void {
    expect(Route::has('horizon.index'))->toBeTrue();
});

test('viewHorizon gate allows super_admin users', function (): void {
    Role::create(['name' => 'super_admin']);
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');

    expect(Gate::forUser($admin)->allows('viewHorizon'))->toBeTrue();
});

test('viewHorizon gate denies non-super-admin users', function (): void {
    $user = User::factory()->create();

    expect(Gate::forUser($user)->allows('viewHorizon'))->toBeFalse();
});
