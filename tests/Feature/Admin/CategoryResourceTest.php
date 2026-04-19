<?php

declare(strict_types=1);

use App\Filament\Resources\Categories\CategoryResource;
use App\Models\Category;
use App\Models\User;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    $this->withoutVite();
    Role::findOrCreate('super_admin');
});

test('super_admin accesses the categories resource index page', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');

    $this->actingAs($admin)
        ->get('/console/categories')
        ->assertSuccessful();
});

test('guests are redirected from the categories resource to login', function (): void {
    $this->get('/console/categories')->assertRedirect('/console/login');
});

test('super_admin can render the create category form', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');

    $this->actingAs($admin)
        ->get('/console/categories/create')
        ->assertSuccessful();
});

test('CategoryResource binds to the Category model', function (): void {
    expect(CategoryResource::getModel())->toBe(Category::class);
});
