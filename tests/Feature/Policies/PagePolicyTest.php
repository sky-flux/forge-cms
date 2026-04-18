<?php

declare(strict_types=1);

use App\Models\Page;
use App\Models\User;
use App\Policies\PagePolicy;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    foreach (['admin', 'editor', 'author', 'super_admin'] as $roleName) {
        Role::findOrCreate($roleName);
    }
});

test('guests can view published pages but not drafts', function (): void {
    $published = Page::factory()->published()->create();
    $draft = Page::factory()->create();

    $policy = new PagePolicy;

    expect($policy->view(null, $published))->toBeTrue();
    expect($policy->view(null, $draft))->toBeFalse();
});

test('author can update their own page but not others', function (): void {
    $author = User::factory()->create();
    $author->assignRole('author');

    $ownPage = Page::factory()->for($author)->create();
    $othersPage = Page::factory()->create();

    expect($author->can('update', $ownPage))->toBeTrue();
    expect($author->can('update', $othersPage))->toBeFalse();
});

test('editor can update any page', function (): void {
    $editor = User::factory()->create();
    $editor->assignRole('editor');

    $page = Page::factory()->create();

    expect($editor->can('update', $page))->toBeTrue();
    expect($editor->can('delete', $page))->toBeTrue();
    expect($editor->can('forceDelete', $page))->toBeFalse();
});

test('admin can forceDelete, editor cannot', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $editor = User::factory()->create();
    $editor->assignRole('editor');

    $page = Page::factory()->create();

    expect($admin->can('forceDelete', $page))->toBeTrue();
    expect($editor->can('forceDelete', $page))->toBeFalse();
});

test('plain user cannot create pages but author / editor / admin can', function (): void {
    $plain = User::factory()->create();
    $author = User::factory()->create();
    $author->assignRole('author');
    $editor = User::factory()->create();
    $editor->assignRole('editor');
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    expect($plain->can('create', Page::class))->toBeFalse();
    expect($author->can('create', Page::class))->toBeTrue();
    expect($editor->can('create', Page::class))->toBeTrue();
    expect($admin->can('create', Page::class))->toBeTrue();
});

test('super_admin bypasses PagePolicy via Gate::before', function (): void {
    $superAdmin = User::factory()->create();
    $superAdmin->assignRole('super_admin');

    $othersPage = Page::factory()->create();

    expect($superAdmin->can('update', $othersPage))->toBeTrue();
    expect($superAdmin->can('forceDelete', $othersPage))->toBeTrue();
});
