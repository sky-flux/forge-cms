<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\PostStatus;
use App\Models\Page;
use App\Models\User;

class PagePolicy
{
    public function viewAny(?User $user): bool
    {
        return true;
    }

    public function view(?User $user, Page $page): bool
    {
        if ($page->status === PostStatus::Published) {
            return true;
        }

        return $user !== null
            && ($user->hasAnyRole(['admin', 'editor']) || $user->id === $page->user_id);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'editor', 'author']);
    }

    public function update(User $user, Page $page): bool
    {
        if ($user->hasAnyRole(['admin', 'editor'])) {
            return true;
        }

        return $user->hasRole('author') && $user->id === $page->user_id;
    }

    public function delete(User $user, Page $page): bool
    {
        if ($user->hasAnyRole(['admin', 'editor'])) {
            return true;
        }

        return $user->hasRole('author') && $user->id === $page->user_id;
    }

    public function restore(User $user, Page $page): bool
    {
        return $user->hasAnyRole(['admin', 'editor']);
    }

    public function forceDelete(User $user, Page $page): bool
    {
        return $user->hasRole('admin');
    }
}
