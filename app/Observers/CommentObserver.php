<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Comment;

class CommentObserver
{
    public function saving(Comment $comment): void
    {
        if ($comment->isDirty('body')) {
            $comment->body_html = nl2br(e((string) $comment->body));
        }
    }
}
