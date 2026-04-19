<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Enums\CommentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCommentRequest;
use App\Models\Comment;
use App\Models\Page;
use App\Models\Post;
use App\Support\CommentIpHasher;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class CommentController extends Controller
{
    public function storeForPost(StoreCommentRequest $request, Post $post, CommentIpHasher $hasher): RedirectResponse
    {
        return $this->persist($request, $post, $hasher);
    }

    public function storeForPage(StoreCommentRequest $request, Page $page, CommentIpHasher $hasher): RedirectResponse
    {
        return $this->persist($request, $page, $hasher);
    }

    private function persist(StoreCommentRequest $request, Model $commentable, CommentIpHasher $hasher): RedirectResponse
    {
        Gate::authorize('view', $commentable);

        abort_unless($commentable->is_comments_enabled, 403, 'Comments are disabled on this content.');

        $user = $request->user();
        $body = $request->string('body')->value();
        $requireModeration = (bool) config('forge.comments.require_moderation', true);

        Comment::create([
            'commentable_type' => $commentable::class,
            'commentable_id' => $commentable->id,
            'parent_id' => $request->integer('parent_id') ?: null,
            'user_id' => $user?->id,
            'guest_name' => $user ? null : $request->string('guest_name')->value(),
            'guest_email' => $user ? null : $request->string('guest_email')->value(),
            'guest_ip_hash' => $hasher->hash($request->ip() ?? '0.0.0.0'),
            'user_agent' => Str::limit((string) $request->userAgent(), 500, ''),
            'body' => $body,
            'status' => $requireModeration ? CommentStatus::Pending : CommentStatus::Approved,
            'approved_at' => $requireModeration ? null : now(),
        ]);

        return back()->with('success', 'Comment submitted.');
    }
}
