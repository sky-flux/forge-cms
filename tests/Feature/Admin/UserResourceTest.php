<?php

declare(strict_types=1);

use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    Role::findOrCreate('super_admin');
    Role::findOrCreate('admin');
});

test('UserResource binds to the User model and lives under 系统', function (): void {
    expect(UserResource::getModel())->toBe(User::class)
        ->and(UserResource::getNavigationGroup())->toBe('系统')
        ->and(UserResource::getNavigationSort())->toBe(1);
});

test('super_admin can access the users index page', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');

    $this->actingAs($admin)
        ->get('/admin/users')
        ->assertSuccessful();
});

test('guests are redirected from /admin/users to login', function (): void {
    $this->get('/admin/users')->assertRedirect('/admin/login');
});

test('super_admin creates a user with name, email, password, and roles', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');

    Livewire::actingAs($admin)
        ->test(CreateUser::class)
        ->fillForm([
            'name' => 'New Editor',
            'email' => 'editor@example.com',
            'password' => 'secret-password-123',
            'password_confirmation' => 'secret-password-123',
            'roles' => [Role::findByName('admin')->id],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $created = User::where('email', 'editor@example.com')->firstOrFail();
    expect($created->name)->toBe('New Editor')
        ->and($created->hasRole('admin'))->toBeTrue()
        ->and(Hash::check('secret-password-123', $created->password))->toBeTrue();
});

test('editing a user persists role changes without changing password unless provided', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');

    $target = User::factory()->create(['password' => bcrypt('keep-this-pw')]);

    Livewire::actingAs($admin)
        ->test(EditUser::class, ['record' => $target->getRouteKey()])
        ->fillForm([
            'name' => $target->name,
            'email' => $target->email,
            'roles' => [Role::findByName('admin')->id],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $target->refresh();
    expect($target->hasRole('admin'))->toBeTrue()
        ->and(Hash::check('keep-this-pw', $target->password))->toBeTrue();
});

test('users index lists existing users with their primary role', function (): void {
    $admin = User::factory()->create(['name' => 'Adam']);
    $admin->assignRole('super_admin');

    $editor = User::factory()->create(['name' => 'Edie']);
    $editor->assignRole('admin');

    Livewire::actingAs($admin)
        ->test(ListUsers::class)
        ->assertCanSeeTableRecords([$admin, $editor])
        ->assertTableColumnExists('name')
        ->assertTableColumnExists('email')
        ->assertTableColumnExists('roles.name');
});

test('users index can filter by role', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');

    $editor = User::factory()->create();
    $editor->assignRole('admin');

    Livewire::actingAs($admin)
        ->test(ListUsers::class)
        ->filterTable('roles', Role::findByName('admin')->id)
        ->assertCanSeeTableRecords([$editor])
        ->assertCanNotSeeTableRecords([$admin]);
});

test('non-super_admin cannot edit a super_admin user', function (): void {
    $editor = User::factory()->create();
    $editor->assignRole('admin');

    $target = User::factory()->create();
    $target->assignRole('super_admin');

    expect($editor->can('update', $target))->toBeFalse();
});

test('non-super_admin cannot strip super_admin role from a super_admin user via update', function (): void {
    $editor = User::factory()->create();
    $editor->assignRole('admin');

    $target = User::factory()->create();
    $target->assignRole('super_admin');

    Livewire::actingAs($editor)
        ->test(EditUser::class, ['record' => $target->getRouteKey()])
        ->assertForbidden();
});

test('super_admin can edit any user including other super_admins', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');

    $other = User::factory()->create();
    $other->assignRole('super_admin');

    expect($admin->can('update', $other))->toBeTrue();
});

test('non-super_admin cannot delete a super_admin user', function (): void {
    $editor = User::factory()->create();
    $editor->assignRole('admin');

    $target = User::factory()->create();
    $target->assignRole('super_admin');

    expect($editor->can('delete', $target))->toBeFalse();
});

test('non-super_admin cannot grant super_admin role to a regular user via the form', function (): void {
    $editor = User::factory()->create();
    $editor->assignRole('admin');

    $target = User::factory()->create();

    Livewire::actingAs($editor)
        ->test(EditUser::class, ['record' => $target->getRouteKey()])
        ->fillForm([
            'name' => $target->name,
            'email' => $target->email,
            'roles' => [Role::findByName('super_admin')->id, Role::findByName('admin')->id],
        ])
        ->call('save');

    $target->refresh();
    expect($target->hasRole('super_admin'))->toBeFalse();
});
