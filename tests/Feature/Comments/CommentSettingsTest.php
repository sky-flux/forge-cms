<?php

declare(strict_types=1);

use App\Enums\CommentStatus;
use App\Models\Comment;
use App\Models\Post;
use App\Settings\CommentSettings;

beforeEach(function (): void {
    $this->withoutVite();
});

test('CommentSettings resolves with defaults', function (): void {
    $settings = app(CommentSettings::class);

    expect($settings->default_status)->toBe('Pending')
        ->and($settings->max_depth)->toBe(3)
        ->and($settings->throttle_per_minute)->toBe(3)
        ->and($settings->allow_guests)->toBeTrue()
        ->and($settings->honeypot_enabled)->toBeTrue()
        ->and($settings->notify_author_on_reply)->toBeFalse();
});

test('comment status defaults from settings when not explicitly set', function (): void {
    $settings = app(CommentSettings::class);
    $settings->default_status = 'Approved';
    $settings->save();

    $post = Post::factory()->published()->create();

    $comment = new Comment([
        'body' => 'Hello',
        'guest_name' => 'Anon',
        'guest_email' => 'a@b.co',
    ]);
    $comment->commentable()->associate($post);
    $comment->save();

    expect($comment->fresh()->status)->toBe(CommentStatus::Approved);
});

test('depth guard uses CommentSettings max_depth', function (): void {
    $settings = app(CommentSettings::class);
    $settings->max_depth = 2;
    $settings->save();

    $post = Post::factory()->published()->create();
    $l1 = Comment::factory()->for($post, 'commentable')->approved()->create(['parent_id' => null]);
    $l2 = Comment::factory()->for($post, 'commentable')->approved()->create(['parent_id' => $l1->id]);

    $this->post(route('posts.comments.store', ['post' => $post]), [
        'guest_name' => 'Jane',
        'guest_email' => 'j@b.co',
        'body' => 'Too deep.',
        'parent_id' => $l2->id,
    ])->assertSessionHasErrors('parent_id');
});
