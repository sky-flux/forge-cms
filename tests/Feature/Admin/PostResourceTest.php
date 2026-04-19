<?php

declare(strict_types=1);

use App\Filament\Resources\Posts\Pages\ListPosts;
use App\Filament\Resources\Posts\PostResource;
use App\Models\Post;
use App\Models\User;
use Livewire\Livewire;
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
        ->get('/console/posts')
        ->assertSuccessful();
});

test('guests are redirected from the posts resource to login', function (): void {
    $this->get('/console/posts')->assertRedirect('/console/login');
});

test('super_admin can render the create form page', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');
    $admin->assignRole('admin');

    $this->actingAs($admin)
        ->get('/console/posts/create')
        ->assertSuccessful();
});

test('PostResource binds to the Post model', function (): void {
    expect(PostResource::getModel())->toBe(Post::class);
});

test('trashed filter exposes soft-deleted posts', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');
    $admin->assignRole('admin');

    $post = Post::factory()->create();
    $post->delete();

    Livewire::actingAs($admin)
        ->test(ListPosts::class)
        ->assertCanNotSeeTableRecords([$post])
        ->filterTable('trashed', 'with')
        ->assertCanSeeTableRecords([$post]);
});
