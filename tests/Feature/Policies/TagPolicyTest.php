<?php

declare(strict_types=1);

use App\Models\Tag;
use App\Models\User;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    foreach (['admin', 'editor', 'author', 'super_admin'] as $role) {
        Role::findOrCreate($role);
    }
});

test('editor can update tag; author cannot; only admin can delete', function (): void {
    $author = User::factory()->create()->assignRole('author');
    $editor = User::factory()->create()->assignRole('editor');
    $admin = User::factory()->create()->assignRole('admin');
    $tag = Tag::factory()->create();

    expect($author->can('update', $tag))->toBeFalse();
    expect($editor->can('update', $tag))->toBeTrue();
    expect($admin->can('update', $tag))->toBeTrue();

    expect($author->can('delete', $tag))->toBeFalse();
    expect($editor->can('delete', $tag))->toBeFalse();
    expect($admin->can('delete', $tag))->toBeTrue();
});

test('author can create but not update or delete', function (): void {
    $author = User::factory()->create()->assignRole('author');
    $tag = Tag::factory()->create();

    expect($author->can('create', Tag::class))->toBeTrue();
    expect($author->can('update', $tag))->toBeFalse();
    expect($author->can('delete', $tag))->toBeFalse();
});
