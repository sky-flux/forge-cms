<?php

declare(strict_types=1);

use App\Models\Page;
use App\Models\Post;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->withoutVite();
});

test('search page renders empty state when q is absent', function (): void {
    $this->get('/search')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Search')
            ->where('query', null)
            ->where('posts.data', [])
            ->where('pages.data', [])
        );
});

test('search returns matching published posts', function (): void {
    $match = Post::factory()->published()->create(['title' => 'Unique Laravel Tip']);
    Post::factory()->published()->create(['title' => 'Unrelated']);

    $this->get('/search?q=Laravel')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Search')
            ->where('query', 'Laravel')
            ->has('posts.data', 1)
            ->where('posts.data.0.title', $match->title)
        );
});

test('search returns matching published pages', function (): void {
    $page = Page::factory()->published()->create(['title' => 'About Forge']);

    $this->get('/search?q=Forge')
        ->assertOk()
        ->assertInertia(fn (Assert $inertia) => $inertia
            ->component('Search')
            ->has('pages.data', 1)
            ->where('pages.data.0.title', $page->title)
        );
});
