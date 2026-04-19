<?php

declare(strict_types=1);

use App\Filament\Resources\DictionaryTypes\DictionaryTypeResource;
use App\Filament\Resources\DictionaryTypes\Pages\CreateDictionaryType;
use App\Filament\Resources\DictionaryTypes\Pages\ListDictionaryTypes;
use App\Models\DictionaryType;
use App\Models\User;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    $this->withoutVite();
    Role::findOrCreate('super_admin');
});

test('DictionaryTypeResource binds to DictionaryType under 系统', function (): void {
    expect(DictionaryTypeResource::getModel())->toBe(DictionaryType::class)
        ->and(DictionaryTypeResource::getNavigationGroup())->toBe('系统')
        ->and(DictionaryTypeResource::getNavigationSort())->toBe(3);
});

test('super_admin can list dictionary types', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');

    DictionaryType::factory()->count(2)->create();

    Livewire::actingAs($admin)
        ->test(ListDictionaryTypes::class)
        ->assertCanSeeTableRecords(DictionaryType::all());
});

test('super_admin can create a dictionary type', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');

    Livewire::actingAs($admin)
        ->test(CreateDictionaryType::class)
        ->fillForm([
            'code' => 'post_visibility',
            'name' => 'Post Visibility',
            'remark' => 'Controls public visibility of posts',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(DictionaryType::where('code', 'post_visibility')->exists())->toBeTrue();
});

test('guests are redirected from /admin/dictionary-types to login', function (): void {
    $this->get('/admin/dictionary-types')->assertRedirect('/admin/login');
});

test('dictionary type code must be unique', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');

    DictionaryType::factory()->create(['code' => 'taken']);

    Livewire::actingAs($admin)
        ->test(CreateDictionaryType::class)
        ->fillForm([
            'code' => 'taken',
            'name' => 'Anything',
        ])
        ->call('create')
        ->assertHasFormErrors(['code']);
});
