<?php

declare(strict_types=1);

use App\Models\Category;
use App\Models\Page;
use App\Models\Post;
use App\Models\Tag;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->withoutVite();
});

test('home page exposes canonical + ogImage props', function (): void {
    $this->get(route('home'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('canonical')
            ->where('canonical', route('home'))
            ->has('ogImage')
        );
});

test('posts index exposes canonical props', function (): void {
    $this->get(route('posts.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('canonical', route('posts.index'))
            ->has('ogImage')
        );
});

test('post show exposes canonical for the specific post', function (): void {
    $post = Post::factory()->published()->create();

    $this->get(route('posts.show', ['post' => $post]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('canonical', route('posts.show', ['post' => $post]))
            ->has('ogImage')
        );
});

test('page show exposes canonical for the specific page', function (): void {
    $page = Page::factory()->published()->create(['slug' => 'about']);

    $this->get(route('pages.show', ['page' => $page]))
        ->assertInertia(fn (Assert $inertia) => $inertia
            ->where('canonical', route('pages.show', ['page' => $page]))
            ->has('ogImage')
        );
});

test('category show exposes canonical for the specific category', function (): void {
    $category = Category::factory()->create(['slug' => 'news']);

    $this->get(route('categories.show', ['category' => $category]))
        ->assertInertia(fn (Assert $p) => $p
            ->where('canonical', route('categories.show', ['category' => $category]))
            ->has('ogImage')
        );
});

test('tag show exposes canonical for the specific tag', function (): void {
    $tag = Tag::factory()->create(['slug' => 'laravel']);

    $this->get(route('tags.show', ['tag' => $tag]))
        ->assertInertia(fn (Assert $p) => $p
            ->where('canonical', route('tags.show', ['tag' => $tag]))
            ->has('ogImage')
        );
});

test('search page exposes canonical', function (): void {
    $this->get(route('search', ['q' => 'laravel']))
        ->assertInertia(fn (Assert $p) => $p
            ->where('canonical', route('search', ['q' => 'laravel']))
            ->has('ogImage')
        );
});
