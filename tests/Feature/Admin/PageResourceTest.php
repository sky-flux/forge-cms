<?php

declare(strict_types=1);

use App\Filament\Resources\Pages\PageResource;
use App\Models\Page;
use App\Models\User;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    $this->withoutVite();
    Role::findOrCreate('super_admin');
});

test('super_admin accesses the pages resource index page', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');

    $this->actingAs($admin)
        ->get('/admin/pages')
        ->assertSuccessful();
});

test('guests are redirected from the pages resource to login', function (): void {
    $this->get('/admin/pages')->assertRedirect('/admin/login');
});

test('super_admin can render the create form page', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');

    $this->actingAs($admin)
        ->get('/admin/pages/create')
        ->assertSuccessful();
});

test('PageResource binds to the Page model', function (): void {
    expect(PageResource::getModel())->toBe(Page::class);
});
