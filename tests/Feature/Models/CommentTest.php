<?php

declare(strict_types=1);

use App\Enums\CommentStatus;
use App\Models\Comment;
use App\Models\Page;
use App\Models\Post;
use App\Models\User;

test('comment factory creates a pending comment by default', function (): void {
    $comment = Comment::factory()->create();

    expect($comment->status)->toBe(CommentStatus::Pending);
    expect($comment->uuid)->toBeString()->not->toBeEmpty();
});

test('comment attaches polymorphically to a post', function (): void {
    $post = Post::factory()->create();
    $comment = Comment::factory()->for($post, 'commentable')->create();

    expect($comment->commentable->is($post))->toBeTrue();
    expect($post->comments()->count())->toBe(1);
});

test('comment attaches polymorphically to a page', function (): void {
    $page = Page::factory()->create();
    $comment = Comment::factory()->for($page, 'commentable')->create();

    expect($comment->commentable->is($page))->toBeTrue();
    expect($page->comments()->count())->toBe(1);
});

test('approvedComments scope on Post filters by status', function (): void {
    $post = Post::factory()->create();
    Comment::factory()->for($post, 'commentable')->approved()->count(2)->create();
    Comment::factory()->for($post, 'commentable')->create();

    expect($post->approvedComments()->count())->toBe(2);
});

test('comment supports nested replies via parent_id', function (): void {
    $parent = Comment::factory()->create();
    $child = Comment::factory()->create(['parent_id' => $parent->id]);

    expect($child->parent->is($parent))->toBeTrue();
    expect($parent->children()->count())->toBe(1);
});

test('deleting a parent comment cascades to children', function (): void {
    $parent = Comment::factory()->create();
    Comment::factory()->create(['parent_id' => $parent->id]);
    Comment::factory()->create(['parent_id' => $parent->id]);

    $parent->delete();

    expect(Comment::count())->toBe(0);
});

test('guest comment has user_id null and guest fields populated', function (): void {
    $comment = Comment::factory()->guest('Alice', 'alice@example.com')->create();

    expect($comment->isGuest())->toBeTrue();
    expect($comment->user_id)->toBeNull();
    expect($comment->guest_name)->toBe('Alice');
    expect($comment->guest_email)->toBe('alice@example.com');
});

test('authenticated user comment has user_id set and guest fields null', function (): void {
    $user = User::factory()->create();
    $comment = Comment::factory()->byUser($user)->create();

    expect($comment->isGuest())->toBeFalse();
    expect($comment->user_id)->toBe($user->id);
    expect($comment->guest_name)->toBeNull();
});

test('authorName returns user name for auth comment, guest_name for guest', function (): void {
    $user = User::factory()->create(['name' => 'Alice']);
    $authComment = Comment::factory()->byUser($user)->create();
    $guestComment = Comment::factory()->guest('Bob')->create();

    expect($authComment->authorName())->toBe('Alice');
    expect($guestComment->authorName())->toBe('Bob');
});

test('pending and approved scopes filter correctly', function (): void {
    Comment::factory()->count(2)->create();
    Comment::factory()->approved()->count(3)->create();
    Comment::factory()->spam()->create();

    expect(Comment::pending()->count())->toBe(2);
    expect(Comment::approved()->count())->toBe(3);
    expect(Comment::spam()->count())->toBe(1);
});
