<?php

declare(strict_types=1);

use App\Enums\CommentStatus;
use App\Models\Comment;
use App\Models\Page;
use App\Models\Post;
use App\Models\User;

beforeEach(function (): void {
    $this->withoutVite();
});

test('guest can submit a comment on a published post — creates pending', function (): void {
    $post = Post::factory()->published()->create();

    $this->post("/posts/{$post->uuid}/comments", [
        'body' => 'Great article!',
        'guest_name' => 'Alice',
        'guest_email' => 'alice@example.com',
    ])->assertRedirect();

    $comment = Comment::first();
    expect($comment)->not->toBeNull();
    expect($comment->status)->toBe(CommentStatus::Pending);
    expect($comment->user_id)->toBeNull();
    expect($comment->guest_name)->toBe('Alice');
    expect($comment->commentable->is($post))->toBeTrue();
});

test('authenticated user can submit a comment without guest fields', function (): void {
    $user = User::factory()->create();
    $post = Post::factory()->published()->create();

    $this->actingAs($user)
        ->post("/posts/{$post->uuid}/comments", [
            'body' => 'Thanks for sharing.',
        ])->assertRedirect();

    $comment = Comment::first();
    expect($comment->user_id)->toBe($user->id);
    expect($comment->guest_name)->toBeNull();
});

test('comment on page also works via slug-bound route', function (): void {
    $page = Page::factory()->published()->create();

    $this->post("/pages/{$page->slug}/comments", [
        'body' => 'Page feedback.',
        'guest_name' => 'Bob',
        'guest_email' => 'bob@example.com',
    ])->assertRedirect();

    expect(Comment::count())->toBe(1);
    expect(Comment::first()->commentable->is($page))->toBeTrue();
});

test('comments-disabled post returns 403', function (): void {
    $post = Post::factory()->published()->create(['is_comments_enabled' => false]);

    $this->post("/posts/{$post->uuid}/comments", [
        'body' => 'Attempt',
        'guest_name' => 'Bot',
        'guest_email' => 'bot@example.com',
    ])->assertForbidden();

    expect(Comment::count())->toBe(0);
});

test('IP hash is HMAC-SHA256 not plain SHA256', function (): void {
    $post = Post::factory()->published()->create();

    $this->withServerVariables(['REMOTE_ADDR' => '1.2.3.4'])
        ->post("/posts/{$post->uuid}/comments", [
            'body' => 'Check HMAC.',
            'guest_name' => 'Alice',
            'guest_email' => 'alice@example.com',
        ])->assertRedirect();

    $plainSha = hash('sha256', '1.2.3.4');
    $hmacSha = hash_hmac('sha256', '1.2.3.4', 'test-hmac-secret-not-for-production');

    $stored = Comment::first()->guest_ip_hash;
    expect($stored)->toHaveLength(64);
    expect($stored)->not->toBe($plainSha);
    expect($stored)->toBe($hmacSha);
});

test('rate limit of 3 per minute kicks in on the 4th submission', function (): void {
    $post = Post::factory()->published()->create();

    for ($i = 1; $i <= 3; $i++) {
        $this->post("/posts/{$post->uuid}/comments", [
            'body' => "Comment {$i}",
            'guest_name' => 'Alice',
            'guest_email' => 'alice@example.com',
        ])->assertRedirect();
    }

    // 4th should be throttled
    $this->post("/posts/{$post->uuid}/comments", [
        'body' => 'Comment 4',
        'guest_name' => 'Alice',
        'guest_email' => 'alice@example.com',
    ])->assertStatus(429);
});

test('honeypot filled submissions are silently discarded', function (): void {
    // Disable randomized field name so we can target the honeypot field directly.
    config()->set('honeypot.randomize_name_field_name', false);
    config()->set('honeypot.valid_from_timestamp', false);

    $post = Post::factory()->published()->create();
    $honeypotField = config('honeypot.name_field_name');

    $this->post("/posts/{$post->uuid}/comments", [
        'body' => 'Bot comment',
        'guest_name' => 'Bot',
        'guest_email' => 'bot@example.com',
        $honeypotField => 'filled-by-bot',
    ])->assertOk();

    // Honeypot middleware short-circuits before controller — no Comment created
    expect(Comment::count())->toBe(0);
});

test('body validation rejects too-short content', function (): void {
    $post = Post::factory()->published()->create();

    $this->post("/posts/{$post->uuid}/comments", [
        'body' => 'X', // too short
        'guest_name' => 'Alice',
        'guest_email' => 'alice@example.com',
    ])->assertSessionHasErrors('body');

    expect(Comment::count())->toBe(0);
});
