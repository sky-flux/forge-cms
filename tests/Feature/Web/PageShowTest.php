<?php

declare(strict_types=1);

use App\Models\Page;
use App\Models\User;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    $this->withoutVite();
    foreach (['super_admin', 'admin', 'editor', 'author'] as $role) {
        Role::findOrCreate($role);
    }
});

test('guest reads a published page by slug', function (): void {
    $page = Page::factory()->published()->create(['title' => 'About Us']);

    $this->get("/pages/{$page->slug}")
        ->assertSuccessful()
        ->assertInertia(fn ($inertia) => $inertia
            ->component('Pages/Show', false)
            ->where('page.title', 'About Us')
            ->where('page.slug', $page->slug)
            ->has('page.bodyHtml')
        );
});

test('guest gets 403 on a draft page', function (): void {
    $page = Page::factory()->create(); // draft

    $this->get("/pages/{$page->slug}")->assertForbidden();
});

test('author can preview their own draft page', function (): void {
    $author = User::factory()->create();
    $author->assignRole('author');
    $draft = Page::factory()->for($author)->create();

    $this->actingAs($author)
        ->get("/pages/{$draft->slug}")
        ->assertSuccessful();
});

test('page route binds by slug not uuid', function (): void {
    $page = Page::factory()->published()->create();

    // URL with uuid should NOT resolve (slug-only binding)
    $this->get("/pages/{$page->uuid}")->assertNotFound();

    // URL with slug resolves
    $this->get("/pages/{$page->slug}")->assertSuccessful();
});
