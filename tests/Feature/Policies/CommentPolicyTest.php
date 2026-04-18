<?php

declare(strict_types=1);

use App\Models\Comment;
use App\Models\User;
use App\Policies\CommentPolicy;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    foreach (['admin', 'editor', 'author', 'super_admin'] as $role) {
        Role::findOrCreate($role);
    }
});

test('editor can approve a pending comment; author cannot', function (): void {
    $editor = User::factory()->create()->assignRole('editor');
    $author = User::factory()->create()->assignRole('author');
    $comment = Comment::factory()->create();

    expect($editor->can('approve', $comment))->toBeTrue();
    expect($author->can('approve', $comment))->toBeFalse();
});

test('admin and editor can delete; author cannot', function (): void {
    $admin = User::factory()->create()->assignRole('admin');
    $editor = User::factory()->create()->assignRole('editor');
    $author = User::factory()->create()->assignRole('author');
    $comment = Comment::factory()->create();

    expect($admin->can('delete', $comment))->toBeTrue();
    expect($editor->can('delete', $comment))->toBeTrue();
    expect($author->can('delete', $comment))->toBeFalse();
});

test('guest can view approved comment but not pending', function (): void {
    $policy = new CommentPolicy;
    $approved = Comment::factory()->approved()->create();
    $pending = Comment::factory()->create();

    expect($policy->view(null, $approved))->toBeTrue();
    expect($policy->view(null, $pending))->toBeFalse();
});

test('create is public (gated elsewhere)', function (): void {
    $policy = new CommentPolicy;
    expect($policy->create(null))->toBeTrue();
    expect($policy->create(User::factory()->create()))->toBeTrue();
});

test('super_admin bypasses CommentPolicy via Gate::before', function (): void {
    $superAdmin = User::factory()->create()->assignRole('super_admin');
    $comment = Comment::factory()->create();

    expect($superAdmin->can('approve', $comment))->toBeTrue();
    expect($superAdmin->can('delete', $comment))->toBeTrue();
});
