<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\CommentStatus;
use App\Models\Comment;
use App\Models\User;

class CommentPolicy
{
    public function viewAny(?User $user): bool
    {
        return true;
    }

    public function view(?User $user, Comment $comment): bool
    {
        if ($comment->status === CommentStatus::Approved) {
            return true;
        }

        return $user !== null && $user->hasAnyRole(['admin', 'editor']);
    }

    public function create(?User $user): bool
    {
        return true;
    }

    public function update(User $user, Comment $comment): bool
    {
        return $user->hasAnyRole(['admin', 'editor']);
    }

    public function delete(User $user, Comment $comment): bool
    {
        return $user->hasAnyRole(['admin', 'editor']);
    }

    public function approve(User $user, Comment $comment): bool
    {
        return $user->hasAnyRole(['admin', 'editor']);
    }
}
