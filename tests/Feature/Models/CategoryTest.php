<?php

declare(strict_types=1);

use App\Models\Category;
use App\Models\Post;

test('a category can be created via factory with slug auto-generated', function (): void {
    $category = Category::factory()->create(['name' => 'Tech News']);

    expect($category->uuid)->toBeString()->not->toBeEmpty();
    expect($category->slug)->toBe('tech-news');
    expect($category->getRouteKeyName())->toBe('slug');
});

test('category can have a parent and children', function (): void {
    $parent = Category::factory()->create(['name' => 'Tech']);
    $child = Category::factory()->childOf($parent)->create(['name' => 'PHP']);

    expect($child->parent->is($parent))->toBeTrue();
    expect($parent->children()->count())->toBe(1);
    expect($parent->children()->first()->is($child))->toBeTrue();
});

test('roots scope returns only top-level categories', function (): void {
    $parent = Category::factory()->create();
    Category::factory()->childOf($parent)->create();
    Category::factory()->create();

    $roots = Category::roots()->get();

    expect($roots)->toHaveCount(2);
    expect($roots->every(fn ($c) => $c->parent_id === null))->toBeTrue();
});

test('category has many posts via pivot', function (): void {
    $category = Category::factory()->create();
    $post = Post::factory()->create();

    $category->posts()->attach($post);

    expect($category->posts)->toHaveCount(1);
    expect($post->fresh()->categories)->toHaveCount(1);
});

test('deleting a category cascades the pivot but leaves posts', function (): void {
    $category = Category::factory()->create();
    $post = Post::factory()->create();
    $category->posts()->attach($post);

    $category->delete();

    expect(Post::count())->toBe(1);
    expect(DB::table('post_category')->count())->toBe(0);
});
