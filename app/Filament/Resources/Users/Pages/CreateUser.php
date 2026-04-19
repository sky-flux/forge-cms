<?php

declare(strict_types=1);

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use Filament\Resources\Pages\CreateRecord;
use Spatie\Permission\Models\Role;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (isset($data['roles']) && ! auth()->user()?->hasRole('super_admin')) {
            $superAdminId = Role::findByName('super_admin')->id;
            $data['roles'] = array_values(array_filter(
                $data['roles'],
                fn ($id) => (int) $id !== (int) $superAdminId,
            ));
        }

        return $data;
    }
}
