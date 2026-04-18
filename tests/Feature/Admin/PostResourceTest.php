<?php

declare(strict_types=1);

use App\Filament\Resources\Posts\PostResource;
use App\Models\Post;
use App\Models\User;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    Role::findOrCreate('super_admin');
    Role::findOrCreate('admin');
});

test('super_admin accesses the posts resource index page', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');
    $admin->assignRole('admin');

    $this->actingAs($admin)
        ->get('/admin/posts')
        ->assertSuccessful();
});

test('guests are redirected from the posts resource to login', function (): void {
    $this->get('/admin/posts')->assertRedirect('/admin/login');
});

test('super_admin can render the create form page', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');
    $admin->assignRole('admin');

    $this->actingAs($admin)
        ->get('/admin/posts/create')
        ->assertSuccessful();
});

test('PostResource binds to the Post model', function (): void {
    expect(PostResource::getModel())->toBe(Post::class);
});
