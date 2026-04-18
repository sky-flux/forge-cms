<?php

declare(strict_types=1);

use App\Filament\Resources\Tags\TagResource;
use App\Models\Tag;
use App\Models\User;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    $this->withoutVite();
    Role::findOrCreate('super_admin');
});

test('super_admin accesses the tags resource index page', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');

    $this->actingAs($admin)
        ->get('/admin/tags')
        ->assertSuccessful();
});

test('guests are redirected from the tags resource to login', function (): void {
    $this->get('/admin/tags')->assertRedirect('/admin/login');
});

test('super_admin can render the create tag form', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');

    $this->actingAs($admin)
        ->get('/admin/tags/create')
        ->assertSuccessful();
});

test('TagResource binds to the Tag model', function (): void {
    expect(TagResource::getModel())->toBe(Tag::class);
});
