<?php

declare(strict_types=1);

use App\Models\User;
use Spatie\Permission\Models\Role;

test('can assign a spatie role to a user and query it back', function (): void {
    $role = Role::create(['name' => 'editor']);
    $user = User::factory()->create();

    $user->assignRole($role);

    expect($user->fresh()->hasRole('editor'))->toBeTrue();
});
