<?php

declare(strict_types=1);

use App\Models\Post;
use App\Models\Tag;

test('a tag can be created via factory with slug auto-generated', function (): void {
    $tag = Tag::factory()->create(['name' => 'Laravel']);

    expect($tag->uuid)->toBeString()->not->toBeEmpty();
    expect($tag->slug)->toBe('laravel');
    expect($tag->getRouteKeyName())->toBe('slug');
});

test('tag has many posts via pivot', function (): void {
    $tag = Tag::factory()->create();
    $post = Post::factory()->create();

    $tag->posts()->attach($post);

    expect($tag->posts)->toHaveCount(1);
    expect($post->fresh()->tags)->toHaveCount(1);
});

test('a post can have multiple tags', function (): void {
    $post = Post::factory()->create();
    $t1 = Tag::factory()->create();
    $t2 = Tag::factory()->create();
    $t3 = Tag::factory()->create();

    $post->tags()->attach([$t1->id, $t2->id, $t3->id]);

    expect($post->fresh()->tags)->toHaveCount(3);
});
