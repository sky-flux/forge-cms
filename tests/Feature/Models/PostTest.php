<?php

declare(strict_types=1);

use App\Enums\PostStatus;
use App\Models\Post;
use App\Models\User;

test('PostStatus enum exposes draft, published, scheduled cases with labels', function (): void {
    expect(PostStatus::Draft->value)->toBe('draft');
    expect(PostStatus::Published->value)->toBe('published');
    expect(PostStatus::Scheduled->value)->toBe('scheduled');
    expect(PostStatus::Draft->label())->toBe('草稿');
});

test('a post can be created via the factory and has a uuid + route key', function (): void {
    $post = Post::factory()->create();

    expect($post->uuid)->toBeString()->not->toBeEmpty();
    expect($post->getRouteKeyName())->toBe('uuid');
    expect($post->status)->toBe(PostStatus::Draft);
    expect($post->is_comments_enabled)->toBeTrue();
});

test('published scope returns only posts with status=published and published_at in the past', function (): void {
    $published = Post::factory()->published()->create();
    Post::factory()->create(); // draft
    Post::factory()->scheduled()->create();

    $found = Post::published()->get();

    expect($found)->toHaveCount(1)
        ->and($found->first()->is($published))->toBeTrue();
});

test('draft scope returns only draft posts', function (): void {
    Post::factory()->published()->create();
    $draft = Post::factory()->create();

    $found = Post::draft()->get();

    expect($found)->toHaveCount(1)
        ->and($found->first()->is($draft))->toBeTrue();
});

test('post soft-deletes and can be restored', function (): void {
    $post = Post::factory()->create();
    $post->delete();

    expect(Post::count())->toBe(0);
    expect(Post::withTrashed()->count())->toBe(1);

    $post->restore();

    expect(Post::count())->toBe(1);
});

test('post belongs to its author', function (): void {
    $author = User::factory()->create();
    $post = Post::factory()->for($author)->create();

    expect($post->user->is($author))->toBeTrue();
});
