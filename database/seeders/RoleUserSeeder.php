<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class RoleUserSeeder extends Seeder
{
    /**
     * Seed one user per application role with known local credentials.
     */
    public function run(): void
    {
        $roles = ['super_admin', 'admin', 'editor', 'author'];

        foreach ($roles as $role) {
            $user = User::firstOrCreate(
                ['email' => "{$role}@example.com"],
                [
                    'name' => ucwords(str_replace('_', ' ', $role)),
                    'password' => Hash::make('password'),
                    'email_verified_at' => now(),
                ],
            );

            $user->syncRoles([$role]);
        }
    }
}
