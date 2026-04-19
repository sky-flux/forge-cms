<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Hash;

test('database seeder creates one user per role with known credentials', function (string $role, string $email): void {
    $this->seed(DatabaseSeeder::class);

    $user = User::where('email', $email)->first();

    expect($user)->not->toBeNull()
        ->and($user->hasRole($role))->toBeTrue()
        ->and(Hash::check('password', $user->password))->toBeTrue();
})->with([
    'super_admin' => ['super_admin', 'super_admin@example.com'],
    'admin' => ['admin', 'admin@example.com'],
    'editor' => ['editor', 'editor@example.com'],
    'author' => ['author', 'author@example.com'],
]);
