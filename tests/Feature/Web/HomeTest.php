<?php

declare(strict_types=1);

use App\Models\Page;
use App\Models\Post;

beforeEach(function (): void {
    $this->withoutVite();
});

test('home page returns 200 and renders the Home component', function (): void {
    $this->get('/')
        ->assertSuccessful()
        ->assertInertia(fn ($inertia) => $inertia->component('Home', false));
});

test('home exposes up to 5 latest published posts, drafts excluded', function (): void {
    Post::factory()->published()->count(7)->create();
    Post::factory()->count(3)->create(); // drafts

    $this->get('/')
        ->assertInertia(fn ($inertia) => $inertia
            ->component('Home', false)
            ->has('latestPosts', 5)
        );
});

test('home homepage prop is null when no is_homepage Page exists', function (): void {
    Page::factory()->published()->create(['is_homepage' => false]);

    $this->get('/')
        ->assertInertia(fn ($inertia) => $inertia
            ->where('homepage', null)
        );
});

test('home homepage prop is set when an is_homepage published Page exists', function (): void {
    $homepage = Page::factory()->published()->homepage()->create(['title' => 'Welcome']);

    $this->get('/')
        ->assertInertia(fn ($inertia) => $inertia
            ->where('homepage.title', 'Welcome')
            ->where('homepage.isHomepage', true)
        );
});

test('app.tsx layout resolver no longer references the deleted welcome page', function (): void {
    $source = file_get_contents(resource_path('js/app.tsx'));

    expect($source)->not->toContain("'welcome'")
        ->not->toContain('"welcome"');
});

test('home page returns 200 when no posts and no homepage exist', function (): void {
    $this->get('/')
        ->assertSuccessful()
        ->assertInertia(fn ($inertia) => $inertia
            ->component('Home', false)
            ->where('homepage', null)
            ->has('latestPosts', 0)
        );
});
