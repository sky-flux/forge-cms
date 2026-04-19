<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\DictionaryType;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class DictionaryTypePolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:DictionaryType');
    }

    public function view(AuthUser $authUser, DictionaryType $dictionaryType): bool
    {
        return $authUser->can('View:DictionaryType');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:DictionaryType');
    }

    public function update(AuthUser $authUser, DictionaryType $dictionaryType): bool
    {
        return $authUser->can('Update:DictionaryType');
    }

    public function delete(AuthUser $authUser, DictionaryType $dictionaryType): bool
    {
        return $authUser->can('Delete:DictionaryType');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:DictionaryType');
    }

    public function restore(AuthUser $authUser, DictionaryType $dictionaryType): bool
    {
        return $authUser->can('Restore:DictionaryType');
    }

    public function forceDelete(AuthUser $authUser, DictionaryType $dictionaryType): bool
    {
        return $authUser->can('ForceDelete:DictionaryType');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:DictionaryType');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:DictionaryType');
    }

    public function replicate(AuthUser $authUser, DictionaryType $dictionaryType): bool
    {
        return $authUser->can('Replicate:DictionaryType');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:DictionaryType');
    }
}
