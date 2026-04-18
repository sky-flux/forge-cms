<?php

declare(strict_types=1);

use App\Models\Category;
use App\Models\User;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    foreach (['admin', 'editor', 'author', 'super_admin'] as $role) {
        Role::findOrCreate($role);
    }
});

test('editor can update category; author cannot; only admin can delete', function (): void {
    $author = User::factory()->create()->assignRole('author');
    $editor = User::factory()->create()->assignRole('editor');
    $admin = User::factory()->create()->assignRole('admin');
    $category = Category::factory()->create();

    expect($author->can('update', $category))->toBeFalse();
    expect($editor->can('update', $category))->toBeTrue();
    expect($admin->can('update', $category))->toBeTrue();

    expect($author->can('delete', $category))->toBeFalse();
    expect($editor->can('delete', $category))->toBeFalse();
    expect($admin->can('delete', $category))->toBeTrue();
});

test('author can create but not update or delete', function (): void {
    $author = User::factory()->create()->assignRole('author');
    $category = Category::factory()->create();

    expect($author->can('create', Category::class))->toBeTrue();
    expect($author->can('update', $category))->toBeFalse();
    expect($author->can('delete', $category))->toBeFalse();
});
