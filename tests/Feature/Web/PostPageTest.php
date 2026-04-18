<?php

declare(strict_types=1);

use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    foreach (['super_admin', 'admin', 'editor', 'author'] as $role) {
        Role::findOrCreate($role);
    }

    /*
     * Tests run before Task 40 lands the React pages, so the Vite manifest
     * does not yet reference `resources/js/pages/Posts/Index.tsx` or
     * `Show.tsx`. `withoutVite()` short-circuits manifest lookups in the
     * blade root view so Inertia can still return a full-render response
     * that `assertInertia()` can parse via `$response->viewData('page')`.
     */
    $this->withoutVite();
});

test('guest sees only published posts on /posts index', function (): void {
    Post::factory()->published()->create(['title' => 'Published A']);
    Post::factory()->published()->create(['title' => 'Published B']);
    Post::factory()->create(['title' => 'Draft One']);
    Post::factory()->scheduled()->create(['title' => 'Scheduled One']);

    $this->get('/posts')
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('Posts/Index', false)
            ->has('posts.data', 2)
        );
});

test('guest reads a published post by uuid', function (): void {
    $post = Post::factory()->published()->create(['title' => 'Hello Post']);

    $this->get("/posts/{$post->uuid}")
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('Posts/Show', false)
            ->where('post.uuid', $post->uuid)
            ->where('post.title', 'Hello Post')
            ->has('post.bodyHtml')
        );
});

test('guest gets 403 on a draft post', function (): void {
    $post = Post::factory()->create(); // draft

    $this->get("/posts/{$post->uuid}")->assertForbidden();
});

test('author can preview their own draft', function (): void {
    $author = User::factory()->create();
    $author->assignRole('author');
    $draft = Post::factory()->for($author)->create();

    $this->actingAs($author)
        ->get("/posts/{$draft->uuid}")
        ->assertSuccessful();
});

test('viewing a post increments the view_count', function (): void {
    $post = Post::factory()->published()->create(['view_count' => 5]);

    $this->get("/posts/{$post->uuid}")->assertSuccessful();

    expect($post->fresh()->view_count)->toBe(6);
});

test('index does NOT leak bodyHtml in the list payload', function (): void {
    Post::factory()->published()->create();

    $this->get('/posts')
        ->assertInertia(fn ($page) => $page
            ->has('posts.data.0', fn ($post) => $post
                ->where('title', fn ($title) => ! empty($title))
                ->missing('bodyHtml')
                ->etc()
            )
        );
});

test('show page includes approved comments in props', function (): void {
    $post = Post::factory()->published()->create();
    Comment::factory()->for($post, 'commentable')->approved()->count(2)->create();
    Comment::factory()->for($post, 'commentable')->create(); // pending — should NOT appear

    $this->get("/posts/{$post->uuid}")
        ->assertInertia(fn ($inertia) => $inertia
            ->has('post.comments', 2)
        );
});

test('show page hides comments when is_comments_enabled is false', function (): void {
    $post = Post::factory()->published()->create(['is_comments_enabled' => false]);
    Comment::factory()->for($post, 'commentable')->approved()->create();

    $this->get("/posts/{$post->uuid}")
        ->assertInertia(fn ($inertia) => $inertia
            ->where('post.isCommentsEnabled', false)
        );
});
