<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['admin', 'editor', 'author', 'super_admin'] as $roleName) {
            Role::findOrCreate($roleName);
        }
    }
}
