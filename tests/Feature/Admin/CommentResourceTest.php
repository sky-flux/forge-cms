<?php

declare(strict_types=1);

use App\Filament\Resources\Comments\CommentResource;
use App\Models\Comment;
use App\Models\User;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    $this->withoutVite();
    foreach (['admin', 'editor', 'super_admin'] as $role) {
        Role::findOrCreate($role);
    }
});

test('super_admin accesses the comments resource index page', function (): void {
    $admin = User::factory()->create()->assignRole('super_admin');

    $this->actingAs($admin)
        ->get('/admin/comments')
        ->assertSuccessful();
});

test('guests are redirected from the comments resource to login', function (): void {
    $this->get('/admin/comments')->assertRedirect('/admin/login');
});

test('CommentResource binds to the Comment model', function (): void {
    expect(CommentResource::getModel())->toBe(Comment::class);
});

test('CommentResource does not expose a create route (admins dont create comments)', function (): void {
    $pages = CommentResource::getPages();
    expect($pages)->toHaveKeys(['index', 'edit']);
    expect($pages)->not->toHaveKey('create');
});

test('editor can approve a pending comment via the policy', function (): void {
    $editor = User::factory()->create()->assignRole('editor');
    $pending = Comment::factory()->create();

    expect($editor->can('approve', $pending))->toBeTrue();
});
