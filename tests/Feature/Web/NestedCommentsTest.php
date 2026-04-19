<?php

declare(strict_types=1);

use App\Models\Comment;
use App\Models\Page;
use App\Models\Post;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->withoutVite();
});

test('post show returns nested comment tree up to depth 3', function (): void {
    $post = Post::factory()->published()->create();

    $parent = Comment::factory()->for($post, 'commentable')->approved()->create([
        'parent_id' => null,
    ]);
    $child = Comment::factory()->for($post, 'commentable')->approved()->create([
        'parent_id' => $parent->id,
    ]);
    $grandchild = Comment::factory()->for($post, 'commentable')->approved()->create([
        'parent_id' => $child->id,
    ]);

    $this->get("/posts/{$post->uuid}")
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Posts/Show', false)
            ->has('post.comments', 1)
            ->where('post.comments.0.uuid', $parent->uuid)
            ->has('post.comments.0.children', 1)
            ->where('post.comments.0.children.0.uuid', $child->uuid)
            ->has('post.comments.0.children.0.children', 1)
            ->where('post.comments.0.children.0.children.0.uuid', $grandchild->uuid)
        );
});

test('page show returns nested comment tree up to depth 3', function (): void {
    $page = Page::factory()->published()->create();

    $parent = Comment::factory()->for($page, 'commentable')->approved()->create([
        'parent_id' => null,
    ]);
    $child = Comment::factory()->for($page, 'commentable')->approved()->create([
        'parent_id' => $parent->id,
    ]);
    $grandchild = Comment::factory()->for($page, 'commentable')->approved()->create([
        'parent_id' => $child->id,
    ]);

    $this->get("/pages/{$page->slug}")
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Pages/Show', false)
            ->has('page.comments', 1)
            ->where('page.comments.0.uuid', $parent->uuid)
            ->has('page.comments.0.children', 1)
            ->where('page.comments.0.children.0.uuid', $child->uuid)
            ->has('page.comments.0.children.0.children', 1)
            ->where('page.comments.0.children.0.children.0.uuid', $grandchild->uuid)
        );
});

test('comment submission accepts parent_id for replies', function (): void {
    $post = Post::factory()->published()->create();
    $parent = Comment::factory()->for($post, 'commentable')->approved()->create([
        'parent_id' => null,
    ]);

    $this->post("/posts/{$post->uuid}/comments", [
        'body' => 'Reply to parent.',
        'guest_name' => 'Jane',
        'guest_email' => 'jane@example.com',
        'parent_id' => $parent->id,
    ])->assertRedirect();

    expect(Comment::where('parent_id', $parent->id)->exists())->toBeTrue();
});

test('comment submission rejects depth-4 replies', function (): void {
    $post = Post::factory()->published()->create();
    $level1 = Comment::factory()->for($post, 'commentable')->approved()->create(['parent_id' => null]);
    $level2 = Comment::factory()->for($post, 'commentable')->approved()->create(['parent_id' => $level1->id]);
    $level3 = Comment::factory()->for($post, 'commentable')->approved()->create(['parent_id' => $level2->id]);

    $this->post("/posts/{$post->uuid}/comments", [
        'body' => 'Too deep.',
        'guest_name' => 'Jane',
        'guest_email' => 'jane@example.com',
        'parent_id' => $level3->id,
    ])->assertSessionHasErrors('parent_id');

    expect(Comment::where('parent_id', $level3->id)->exists())->toBeFalse();
});
