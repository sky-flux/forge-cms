<?php

declare(strict_types=1);

namespace App\Observers;

use App\Enums\CommentStatus;
use App\Models\Comment;
use App\Models\User;
use App\Notifications\NewCommentPendingNotification;
use App\Settings\CommentSettings;

class CommentObserver
{
    /**
     * Apply the configured default status when a caller does not explicitly
     * set one. Callers that set `status` explicitly (e.g. CommentController)
     * win over the setting.
     */
    public function creating(Comment $comment): void
    {
        if ($comment->status === null) {
            $name = app(CommentSettings::class)->default_status;
            $comment->status = constant(CommentStatus::class."::{$name}");
        }
    }

    public function saving(Comment $comment): void
    {
        if ($comment->isDirty('body')) {
            $comment->body_html = nl2br(e((string) $comment->body));
        }
    }

    public function created(Comment $comment): void
    {
        if ($comment->status !== CommentStatus::Pending) {
            return;
        }

        User::query()
            ->whereHas('roles', fn ($query) => $query->where('name', 'super_admin'))
            ->get()
            ->each(fn (User $admin) => $admin->notify(
                new NewCommentPendingNotification($comment),
            ));
    }
}
