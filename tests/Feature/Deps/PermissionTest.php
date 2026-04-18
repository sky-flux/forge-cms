<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

it('can assign a spatie role to a user and query it back', function (): void {
    $role = Role::create(['name' => 'editor']);
    $user = User::factory()->create();

    $user->assignRole($role);

    expect($user->fresh()->hasRole('editor'))->toBeTrue();
});
