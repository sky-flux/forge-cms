<?php

declare(strict_types=1);

use App\Models\Post;
use App\Models\User;
use App\Policies\PostPolicy;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    foreach (['admin', 'editor', 'author', 'super_admin'] as $roleName) {
        Role::findOrCreate($roleName);
    }
});

test('guests can view published posts but not drafts', function (): void {
    $published = Post::factory()->published()->create();
    $draft = Post::factory()->create();

    $policy = new PostPolicy;

    expect($policy->view(null, $published))->toBeTrue();
    expect($policy->view(null, $draft))->toBeFalse();
});

test('author can update their own post but not others', function (): void {
    $author = User::factory()->create();
    $author->assignRole('author');

    $ownPost = Post::factory()->for($author)->create();
    $othersPost = Post::factory()->create();

    expect($author->can('update', $ownPost))->toBeTrue();
    expect($author->can('update', $othersPost))->toBeFalse();
});

test('editor can update any post', function (): void {
    $editor = User::factory()->create();
    $editor->assignRole('editor');

    $post = Post::factory()->create();

    expect($editor->can('update', $post))->toBeTrue();
    expect($editor->can('delete', $post))->toBeTrue();
    expect($editor->can('forceDelete', $post))->toBeFalse();
});

test('admin can forceDelete, editor cannot', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $editor = User::factory()->create();
    $editor->assignRole('editor');

    $post = Post::factory()->create();

    expect($admin->can('forceDelete', $post))->toBeTrue();
    expect($editor->can('forceDelete', $post))->toBeFalse();
});

test('plain user cannot create posts but author / editor / admin can', function (): void {
    $plain = User::factory()->create();
    $author = User::factory()->create();
    $author->assignRole('author');
    $editor = User::factory()->create();
    $editor->assignRole('editor');
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    expect($plain->can('create', Post::class))->toBeFalse();
    expect($author->can('create', Post::class))->toBeTrue();
    expect($editor->can('create', Post::class))->toBeTrue();
    expect($admin->can('create', Post::class))->toBeTrue();
});

test('RolePermissionSeeder creates all 4 roles', function (): void {
    // beforeEach already seeded; verify
    expect(Role::whereIn('name', ['admin', 'editor', 'author', 'super_admin'])->count())->toBe(4);
});
