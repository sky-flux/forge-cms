<?php

declare(strict_types=1);

use App\Models\Post;
use App\Models\Tag;

test('guest views published posts in a tag by slug', function (): void {
    $tag = Tag::factory()->create(['name' => 'Laravel']);
    $published = Post::factory()->published()->create();
    $otherPublished = Post::factory()->published()->create();
    $draft = Post::factory()->create();

    $tag->posts()->attach([$published->id, $draft->id]);
    // otherPublished is not on this tag

    $this->withoutVite()
        ->get("/tags/{$tag->slug}")
        ->assertSuccessful()
        ->assertInertia(fn ($inertia) => $inertia
            ->component('Tags/Show', false)
            ->where('tag.slug', 'laravel')
            ->where('tag.name', 'Laravel')
            ->has('posts.data', 1) // only the published one on this tag
        );
});

test('empty tag renders without error', function (): void {
    $tag = Tag::factory()->create();

    $this->withoutVite()
        ->get("/tags/{$tag->slug}")
        ->assertSuccessful()
        ->assertInertia(fn ($inertia) => $inertia
            ->component('Tags/Show', false)
            ->has('posts.data', 0)
        );
});

test('tag slug is the route binding, not uuid', function (): void {
    $tag = Tag::factory()->create();

    $this->withoutVite()->get("/tags/{$tag->uuid}")->assertNotFound();
    $this->withoutVite()->get("/tags/{$tag->slug}")->assertSuccessful();
});

test('a post with multiple tags appears in each archive', function (): void {
    $tagA = Tag::factory()->create();
    $tagB = Tag::factory()->create();
    $post = Post::factory()->published()->create();

    $post->tags()->attach([$tagA->id, $tagB->id]);

    $this->withoutVite()
        ->get("/tags/{$tagA->slug}")
        ->assertInertia(fn ($inertia) => $inertia->has('posts.data', 1));

    $this->withoutVite()
        ->get("/tags/{$tagB->slug}")
        ->assertInertia(fn ($inertia) => $inertia->has('posts.data', 1));
});
