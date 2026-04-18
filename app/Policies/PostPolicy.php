<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\PostStatus;
use App\Models\Post;
use App\Models\User;

class PostPolicy
{
    public function viewAny(?User $user): bool
    {
        return true;
    }

    public function view(?User $user, Post $post): bool
    {
        if ($post->status === PostStatus::Published) {
            return true;
        }

        return $user !== null
            && ($user->hasAnyRole(['admin', 'editor']) || $user->id === $post->user_id);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'editor', 'author']);
    }

    public function update(User $user, Post $post): bool
    {
        if ($user->hasAnyRole(['admin', 'editor'])) {
            return true;
        }

        return $user->hasRole('author') && $user->id === $post->user_id;
    }

    public function delete(User $user, Post $post): bool
    {
        if ($user->hasAnyRole(['admin', 'editor'])) {
            return true;
        }

        return $user->hasRole('author') && $user->id === $post->user_id;
    }

    public function restore(User $user, Post $post): bool
    {
        return $user->hasAnyRole(['admin', 'editor']);
    }

    public function forceDelete(User $user, Post $post): bool
    {
        return $user->hasRole('admin');
    }
}
