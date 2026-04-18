<?php

declare(strict_types=1);

use App\Models\Post;
use App\Models\User;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    foreach (['super_admin', 'admin', 'editor', 'author'] as $role) {
        Role::findOrCreate($role);
    }
});

test('super_admin bypasses PostPolicy::update even on others posts', function (): void {
    $superAdmin = User::factory()->create();
    $superAdmin->assignRole('super_admin');

    $otherAuthor = User::factory()->create();
    $otherAuthor->assignRole('author');
    $othersPost = Post::factory()->for($otherAuthor)->create();

    expect($superAdmin->can('update', $othersPost))->toBeTrue();
    expect($superAdmin->can('delete', $othersPost))->toBeTrue();
    expect($superAdmin->can('forceDelete', $othersPost))->toBeTrue();
});

test('non-super-admin still subject to PostPolicy rules', function (): void {
    $author = User::factory()->create();
    $author->assignRole('author');

    $othersPost = Post::factory()->create();

    // PostPolicy::update requires admin/editor OR author+owner
    expect($author->can('update', $othersPost))->toBeFalse();
});

test('guest (null user) is not silently granted by Gate::before', function (): void {
    $post = Post::factory()->create();

    // Gate::forUser(null) simulates an unauthenticated request
    expect(Gate::forUser(null)->allows('update', $post))->toBeFalse();
});
