<?php

declare(strict_types=1);

use App\Models\Category;
use App\Models\Post;

test('guest views published posts in a category by slug', function (): void {
    $category = Category::factory()->create(['name' => 'Tech News']);
    $published = Post::factory()->published()->create();
    $otherPublished = Post::factory()->published()->create();
    $draft = Post::factory()->create();

    $category->posts()->attach([$published->id, $draft->id]);
    // otherPublished is not in this category

    $this->withoutVite()
        ->get("/categories/{$category->slug}")
        ->assertSuccessful()
        ->assertInertia(fn ($inertia) => $inertia
            ->component('Categories/Show', false)
            ->where('category.slug', 'tech-news')
            ->where('category.name', 'Tech News')
            ->has('posts.data', 1) // only the published one in this category
        );
});

test('empty category renders without error', function (): void {
    $category = Category::factory()->create();

    $this->withoutVite()
        ->get("/categories/{$category->slug}")
        ->assertSuccessful()
        ->assertInertia(fn ($inertia) => $inertia
            ->component('Categories/Show', false)
            ->has('posts.data', 0)
        );
});

test('category slug is the route binding, not uuid', function (): void {
    $category = Category::factory()->create();

    $this->withoutVite()->get("/categories/{$category->uuid}")->assertNotFound();
    $this->withoutVite()->get("/categories/{$category->slug}")->assertSuccessful();
});

test('a post in multiple categories appears in each archive', function (): void {
    $categoryA = Category::factory()->create();
    $categoryB = Category::factory()->create();
    $post = Post::factory()->published()->create();

    $post->categories()->attach([$categoryA->id, $categoryB->id]);

    $this->withoutVite()
        ->get("/categories/{$categoryA->slug}")
        ->assertInertia(fn ($inertia) => $inertia->has('posts.data', 1));

    $this->withoutVite()
        ->get("/categories/{$categoryB->slug}")
        ->assertInertia(fn ($inertia) => $inertia->has('posts.data', 1));
});
