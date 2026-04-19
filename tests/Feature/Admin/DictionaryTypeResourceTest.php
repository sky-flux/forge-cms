<?php

declare(strict_types=1);

use App\Filament\Resources\DictionaryTypes\DictionaryTypeResource;
use App\Filament\Resources\DictionaryTypes\Pages\CreateDictionaryType;
use App\Filament\Resources\DictionaryTypes\Pages\EditDictionaryType;
use App\Filament\Resources\DictionaryTypes\Pages\ListDictionaryTypes;
use App\Filament\Resources\DictionaryTypes\RelationManagers\ItemsRelationManager;
use App\Models\DictionaryItem;
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

test('items relation manager lists items for a type', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');

    $type = DictionaryType::factory()->create();
    $items = DictionaryItem::factory()->count(2)->for($type, 'type')->create();

    Livewire::actingAs($admin)
        ->test(ItemsRelationManager::class, [
            'ownerRecord' => $type,
            'pageClass' => EditDictionaryType::class,
        ])
        ->assertCanSeeTableRecords($items);
});

test('super_admin creates an item under a type via the relation manager', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');

    $type = DictionaryType::factory()->create(['code' => 'visibility']);

    Livewire::actingAs($admin)
        ->test(ItemsRelationManager::class, [
            'ownerRecord' => $type,
            'pageClass' => EditDictionaryType::class,
        ])
        ->callTableAction('create', data: [
            'label' => 'Public',
            'value' => 'public',
            'sort' => 10,
            'is_default' => true,
            'status' => true,
        ])
        ->assertHasNoTableActionErrors();

    expect($type->items()->where('value', 'public')->exists())->toBeTrue();
});

test('item value must be unique within its type', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');

    $type = DictionaryType::factory()->create();
    DictionaryItem::factory()->for($type, 'type')->create(['value' => 'duplicate']);

    Livewire::actingAs($admin)
        ->test(ItemsRelationManager::class, [
            'ownerRecord' => $type,
            'pageClass' => EditDictionaryType::class,
        ])
        ->callTableAction('create', data: [
            'label' => 'Other',
            'value' => 'duplicate',
        ])
        ->assertHasTableActionErrors(['value']);
});
