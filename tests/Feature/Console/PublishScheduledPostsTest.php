<?php

declare(strict_types=1);

use App\Enums\PostStatus;
use App\Models\Post;

test('scheduled posts with past published_at flip to Published', function (): void {
    $post = Post::factory()->create([
        'status' => PostStatus::Scheduled,
        'published_at' => now()->subMinute(),
    ]);

    $this->artisan('posts:publish-scheduled')->assertSuccessful();

    expect($post->fresh()->status)->toBe(PostStatus::Published);
});

test('scheduled posts with future published_at stay Scheduled', function (): void {
    $post = Post::factory()->create([
        'status' => PostStatus::Scheduled,
        'published_at' => now()->addHour(),
    ]);

    $this->artisan('posts:publish-scheduled')->assertSuccessful();

    expect($post->fresh()->status)->toBe(PostStatus::Scheduled);
});

test('draft posts stay Draft regardless of published_at', function (): void {
    $post = Post::factory()->create([
        'status' => PostStatus::Draft,
        'published_at' => now()->subDay(),
    ]);

    $this->artisan('posts:publish-scheduled')->assertSuccessful();

    expect($post->fresh()->status)->toBe(PostStatus::Draft);
});

test('published posts are idempotent', function (): void {
    $post = Post::factory()->create([
        'status' => PostStatus::Published,
        'published_at' => now()->subWeek(),
    ]);
    $originalUpdatedAt = $post->updated_at;

    $this->artisan('posts:publish-scheduled')->assertSuccessful();

    $fresh = $post->fresh();
    expect($fresh->status)->toBe(PostStatus::Published)
        ->and($fresh->updated_at->toIso8601String())->toBe($originalUpdatedAt->toIso8601String());
});

test('command reports how many posts were published', function (): void {
    Post::factory()->count(3)->create([
        'status' => PostStatus::Scheduled,
        'published_at' => now()->subMinute(),
    ]);

    $this->artisan('posts:publish-scheduled')
        ->expectsOutputToContain('3')
        ->assertSuccessful();
});
