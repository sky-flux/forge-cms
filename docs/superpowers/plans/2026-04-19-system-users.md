# UserResource (系统 → 用户) — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a Filament `UserResource` under the 「系统」 nav group so super_admins can list, create, edit, and (soft-)delete users plus assign Spatie roles, with a defense-in-depth policy that prevents non-super_admins from demoting super_admins.

**Architecture:** Standard Filament 4 split-file Resource (`Resource` + `Pages/` + `Schemas/` + `Tables/`). Roles come from the existing `roles()` relation on `User` (Spatie). Authorization via a Shield-generated `UserPolicy` plus a custom `update()` rule. No model changes (User already implements FilamentUser, has HasRoles, etc.).

**Tech Stack:** Filament 4, Spatie Permission 7, Filament Shield 4, Pest 4. Depends on the Foundation plan having merged.

**Spec:** `docs/superpowers/specs/2026-04-19-system-admin-modules.md` §5.2

**Depends on:** `2026-04-19-system-foundation.md` (the 系统 nav group must exist).

---

## Workflow per Task (project-mandated)

Each Task ends in **exactly one commit** via this loop:
1. **TDD** — failing test → red → minimal impl → green
2. **Pint** — `vendor/bin/pint --dirty --format agent`
3. **CR** — dispatch `pr-review-toolkit:code-reviewer` against unstaged diff
4. **FIX** — address every issue (re-run tests after each)
5. **Loop** CR until clean
6. **Commit** — main session only, ONE commit per task

---

## File Structure

| File | Responsibility | Action |
|---|---|---|
| `app/Filament/Resources/Users/UserResource.php` | Resource entry point | Create (via artisan, then edit) |
| `app/Filament/Resources/Users/Pages/{ListUsers,CreateUser,EditUser}.php` | Page classes | Create (via artisan) |
| `app/Filament/Resources/Users/Schemas/UserForm.php` | Form schema | Create (via artisan, then edit) |
| `app/Filament/Resources/Users/Tables/UsersTable.php` | Table schema | Create (via artisan, then edit) |
| `app/Policies/UserPolicy.php` | Authorization | Create (via Shield, then edit for super_admin protection) |
| `database/factories/UserFactory.php` | (verify exists) | Read-only |
| `tests/Feature/Admin/UserResourceTest.php` | Pest tests | Create |

---

### Task 1: Pre-flight inspection

**No commit. Records facts used by Tasks 2–5.**

- [ ] **Step 1: Confirm User has SoftDeletes**

```bash
grep -n 'SoftDeletes' app/Models/User.php
grep -n 'deleted_at' database/migrations/0001_01_01_000000_create_users_table.php
```

Record one of:
- `> User has SoftDeletes` → use Filament's standard SoftDeletingScope filter (TrashedFilter).
- `> User does NOT have SoftDeletes` → omit TrashedFilter; bulk action remains hard-delete only. Add a follow-up task to spec §9.

- [ ] **Step 2: Confirm Spatie roles relation API**

```bash
grep -n 'function roles' vendor/spatie/laravel-permission/src/Traits/HasRoles.php
```

Expected: `roles(): MorphToMany`. Used by `Select::make('roles')->relationship('roles', 'name')`.

- [ ] **Step 3: Confirm RoleResource model class for the assignable list**

```bash
php artisan tinker --execute 'echo get_class(\Spatie\Permission\Models\Role::first());'
```

Records the role model FQCN (likely `Spatie\Permission\Models\Role` or a project-extended subclass). Used in tests.

- [ ] **Step 4: Verify Foundation plan merged**

```bash
git log --oneline | head -5
```
Look for `feat(admin): declare 内容 and 系统 navigation groups`. If absent, STOP — Foundation plan must be in main first.

---

### Task 2: Scaffold UserResource (skeleton + nav group)

**Files:**
- Create: `app/Filament/Resources/Users/UserResource.php` + Pages/Schemas/Tables (via artisan)
- Modify: the generated `UserResource.php` (add nav group, model binding sanity)
- Create: `tests/Feature/Admin/UserResourceTest.php`

#### TDD

- [ ] **Step 1: Generate scaffold**

```bash
php artisan make:filament-resource User --generate --no-interaction
```

This creates `app/Filament/Resources/Users/{UserResource.php, Pages/*, Schemas/UserForm.php, Tables/UsersTable.php}` with auto-detected fields.

- [ ] **Step 2: Add nav group + sort to UserResource.php**

Insert under `protected static ?string $model = User::class;`:

```php
protected static string|\UnitEnum|null $navigationGroup = '系统';

protected static ?int $navigationSort = 1;

protected static ?string $navigationLabel = '用户';

protected static ?string $modelLabel = '用户';

protected static ?string $pluralModelLabel = '用户';
```

- [ ] **Step 3: Write the failing tests**

Create `tests/Feature/Admin/UserResourceTest.php`:

```php
<?php

declare(strict_types=1);

use App\Filament\Resources\Users\UserResource;
use App\Models\User;
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
```

- [ ] **Step 4: Run tests — confirm red**

```bash
php artisan test --compact --filter=UserResourceTest
```
Expected: tests fail because the navigationGroup expectation is `'系统'` (asserted against the property added in Step 2 — should pass) AND because the route returns 403 (no Policy yet). At minimum the guest-redirect test should pass after Step 2.

If all 3 fail: that means the artisan-generated resource defaulted to a different namespace — investigate and fix the FQCN before continuing.

- [ ] **Step 5: Make tests pass**

Step 2 already added the nav group property → first test passes.
For test 2 (super_admin index): no Policy exists yet, so by default Filament denies. **Workaround for this task only:** add a temporary `public static function canAccess(): bool { return true; }` override in UserResource so the index renders. Task 4 replaces this with a real Policy.

Document this temporary override with a `// TEMPORARY: replaced by UserPolicy in Task 4 of plan 2026-04-19-system-users.md` comment.

- [ ] **Step 6: Re-run tests — confirm green**

```bash
php artisan test --compact --filter=UserResourceTest
```
Expected: 3 PASS.

#### Pint

- [ ] **Step 7: Format**

```bash
vendor/bin/pint --dirty --format agent
```

#### CR Loop

- [ ] **Step 8: Dispatch code reviewer**

Prompt:
> Review unstaged diff: a new Filament UserResource was scaffolded under app/Filament/Resources/Users/. Confirm: (a) follows the project's Filament 4 split-file pattern (CategoryResource is the canonical sibling), (b) `$navigationGroup`/`$navigationSort`/labels match Plan §5.2, (c) the temporary `canAccess() { return true; }` is clearly marked for replacement in Task 4, (d) tests use `test()` + `$this->actingAs()` per `forge-cms-overrides.md` §4.1, no `Pest\Laravel` functional imports.

- [ ] **Step 9: Fix flagged issues** (loop with tests + pint).

- [ ] **Step 10: Re-dispatch CR until clean.**

#### Commit

- [ ] **Step 11: Commit**

```bash
git add app/Filament/Resources/Users tests/Feature/Admin/UserResourceTest.php
git commit -m "feat(admin): scaffold UserResource under 系统 nav group"
```

---

### Task 3: Form fields — name/email/password/roles

**Files:**
- Modify: `app/Filament/Resources/Users/Schemas/UserForm.php`
- Modify: `tests/Feature/Admin/UserResourceTest.php`

#### TDD

- [ ] **Step 1: Write the failing test**

Append to `tests/Feature/Admin/UserResourceTest.php`:

```php
use App\Filament\Resources\Users\Pages\CreateUser;
use Livewire\Livewire;

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
        ->and(\Hash::check('secret-password-123', $created->password))->toBeTrue();
});

test('editing a user persists role changes without changing password unless provided', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');

    $target = User::factory()->create(['password' => bcrypt('keep-this-pw')]);

    Livewire::actingAs($admin)
        ->test(\App\Filament\Resources\Users\Pages\EditUser::class, ['record' => $target->getRouteKey()])
        ->fillForm([
            'name' => $target->name,
            'email' => $target->email,
            'roles' => [Role::findByName('admin')->id],
            // password intentionally omitted
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $target->refresh();
    expect($target->hasRole('admin'))->toBeTrue()
        ->and(\Hash::check('keep-this-pw', $target->password))->toBeTrue();
});
```

- [ ] **Step 2: Run tests — confirm red**

```bash
php artisan test --compact --filter='creates a user|persists role changes'
```
Expected: FAIL — generated form lacks roles select, doesn't hash password, treats password as required on edit.

- [ ] **Step 3: Implement UserForm**

Replace the body of `app/Filament/Resources/Users/Schemas/UserForm.php` with:

```php
<?php

declare(strict_types=1);

namespace App\Filament\Resources\Users\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->required()
                ->maxLength(255),

            TextInput::make('email')
                ->email()
                ->required()
                ->unique(ignoreRecord: true)
                ->maxLength(255),

            DateTimePicker::make('email_verified_at')
                ->label('Email verified at')
                ->nullable(),

            // NOTE: do NOT call Hash::make here. User model declares
            // 'password' => 'hashed' in casts() (app/Models/User.php), which
            // hashes on assignment. Manual Hash::make would double-bcrypt and
            // break Hash::check in tests + logins.
            TextInput::make('password')
                ->password()
                ->revealable()
                ->dehydrated(fn (?string $state): bool => filled($state))
                ->required(fn (string $operation): bool => $operation === 'create')
                ->confirmed()
                ->minLength(8),

            TextInput::make('password_confirmation')
                ->password()
                ->revealable()
                ->dehydrated(false)
                ->required(fn (string $operation): bool => $operation === 'create'),

            Select::make('roles')
                ->relationship(
                    'roles',
                    'name',
                    fn (\Illuminate\Database\Eloquent\Builder $query) => auth()->user()?->hasRole('super_admin')
                        ? $query
                        : $query->where('name', '!=', 'super_admin'),
                )
                ->multiple()
                ->preload()
                ->searchable(),
        ]);
    }
}
```

- [ ] **Step 4: Run tests — confirm green**

```bash
php artisan test --compact --filter='creates a user|persists role changes'
```
Expected: PASS.

#### Pint

- [ ] **Step 5: Format**

```bash
vendor/bin/pint --dirty --format agent
```

#### CR Loop

- [ ] **Step 6: Dispatch code reviewer**

Prompt:
> Review unstaged diff for UserForm.php. Verify: (a) password field relies on the User model's `'password' => 'hashed'` cast — it must NOT call `Hash::make` manually (that would double-bcrypt), (b) password is required on create only and left unchanged on edit when empty, (c) `roles` Select uses Spatie's `roles()` relationship correctly with `multiple()->preload()`, (d) email unique rule ignores the current record on edit. Flag any Octane/security concerns. Reference forge-cms-overrides.md and `app/Models/User.php:35-45`.

- [ ] **Step 7: Fix flagged issues** (loop with tests + pint).

- [ ] **Step 8: Re-dispatch CR until clean.**

#### Commit

- [ ] **Step 9: Commit**

```bash
git add app/Filament/Resources/Users/Schemas/UserForm.php tests/Feature/Admin/UserResourceTest.php
git commit -m "feat(admin): UserResource form with roles select and conditional password hashing"
```

---

### Task 4: UsersTable + filters

**Files:**
- Modify: `app/Filament/Resources/Users/Tables/UsersTable.php`
- Modify: `tests/Feature/Admin/UserResourceTest.php`

#### TDD

- [ ] **Step 1: Write the failing test**

Append:

```php
use App\Filament\Resources\Users\Pages\ListUsers;

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
```

- [ ] **Step 2: Run tests — confirm red**

```bash
php artisan test --compact --filter='users index'
```
Expected: FAIL — generated table lacks roles column and filter.

- [ ] **Step 3: Implement UsersTable**

Replace `app/Filament/Resources/Users/Tables/UsersTable.php`:

```php
<?php

declare(strict_types=1);

namespace App\Filament\Resources\Users\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('email')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('roles.name')
                    ->label('Roles')
                    ->badge()
                    ->separator(','),

                IconColumn::make('email_verified_at')
                    ->label('Verified')
                    ->boolean(),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('roles')
                    ->relationship('roles', 'name')
                    ->multiple()
                    ->preload(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('id', 'desc');
    }
}
```

If Task 1 confirmed SoftDeletes is enabled on User, also add `\Filament\Tables\Filters\TrashedFilter::make()` and `\Filament\Actions\RestoreAction::make()` / `ForceDeleteAction::make()` per the existing PostsTable pattern.

- [ ] **Step 4: Run tests — confirm green**

```bash
php artisan test --compact --filter=UserResourceTest
```
Expected: all PASS.

#### Pint

- [ ] **Step 5: Format**

```bash
vendor/bin/pint --dirty --format agent
```

#### CR Loop

- [ ] **Step 6: Dispatch code reviewer**

Prompt:
> Review UsersTable.php diff. Verify: (a) `roles.name` eager-loads via Filament's relationship column (no N+1 — preventLazyLoading is on), (b) SelectFilter uses `relationship('roles','name')` correctly, (c) action set matches sibling resources (PostsTable). Reference forge-cms-overrides.md §2.4.

- [ ] **Step 7: Fix flagged issues** (loop).

- [ ] **Step 8: Re-dispatch CR until clean.**

#### Commit

- [ ] **Step 9: Commit**

```bash
git add app/Filament/Resources/Users/Tables/UsersTable.php tests/Feature/Admin/UserResourceTest.php
git commit -m "feat(admin): UserResource table with role badges and role filter"
```

---

### Task 5: UserPolicy with super_admin protection

**Files:**
- Create: `app/Policies/UserPolicy.php`
- Modify: `app/Filament/Resources/Users/UserResource.php` (remove temporary `canAccess` override from Task 2)
- Modify: `app/Providers/AuthServiceProvider.php` (register policy if not auto-discovered)
- Modify: `tests/Feature/Admin/UserResourceTest.php`

#### TDD

- [ ] **Step 1: Generate Shield policy**

```bash
php artisan shield:generate --resource=UserResource --no-interaction
```

This generates a permission set (view_user, create_user, etc.) and a base `UserPolicy`. Inspect the generated file:

```bash
cat app/Policies/UserPolicy.php
```

- [ ] **Step 2: Write the failing tests**

Append to `tests/Feature/Admin/UserResourceTest.php`:

```php
test('non-super_admin cannot edit a super_admin user', function (): void {
    $editor = User::factory()->create();
    $editor->assignRole('admin'); // 'admin' role has user.update permission via Shield

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
        ->test(\App\Filament\Resources\Users\Pages\EditUser::class, ['record' => $target->getRouteKey()])
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

    // Non-super_admin attempts to attach super_admin via the EditUser form.
    Livewire::actingAs($editor)
        ->test(\App\Filament\Resources\Users\Pages\EditUser::class, ['record' => $target->getRouteKey()])
        ->fillForm([
            'name' => $target->name,
            'email' => $target->email,
            'roles' => [Role::findByName('super_admin')->id, Role::findByName('admin')->id],
        ])
        ->call('save');

    $target->refresh();
    expect($target->hasRole('super_admin'))->toBeFalse();
});
```

- [ ] **Step 3: Run tests — confirm red**

```bash
php artisan test --compact --filter='super_admin'
```
Expected: FAIL — generated UserPolicy lets any user with the permission act on any user, including super_admins.

- [ ] **Step 4: Add the protection rule to UserPolicy**

Edit the generated `app/Policies/UserPolicy.php`. Locate `update()` and `delete()` methods and add a guard at the top of each:

```php
public function update(User $user, User $model): bool
{
    if ($model->hasRole('super_admin') && ! $user->hasRole('super_admin')) {
        return false;
    }

    return $user->can('update_user');
}

public function delete(User $user, User $model): bool
{
    if ($model->hasRole('super_admin') && ! $user->hasRole('super_admin')) {
        return false;
    }

    return $user->can('delete_user');
}
```

(Keep the `view`, `viewAny`, `create`, `restore`, `forceDelete` methods as Shield generated them.)

- [ ] **Step 5: Remove the temporary `canAccess()` override from UserResource**

Open `app/Filament/Resources/Users/UserResource.php` and delete the `canAccess()` method added in Task 2 Step 5. Filament now defers to UserPolicy via Shield.

- [ ] **Step 5b: Add defense-in-depth — strip super_admin from form data for non-super_admins**

Edit both `app/Filament/Resources/Users/Pages/CreateUser.php` and `EditUser.php` — add:

```php
/**
 * @param  array<string, mixed>  $data
 * @return array<string, mixed>
 */
protected function mutateFormDataBeforeSave(array $data): array
{
    // Belt-and-braces: the roles Select already filters the option list,
    // but Livewire state can be manipulated client-side. Strip super_admin
    // here if the actor doesn't have it themselves.
    if (isset($data['roles']) && ! auth()->user()?->hasRole('super_admin')) {
        $superAdminId = \Spatie\Permission\Models\Role::findByName('super_admin')->id;
        $data['roles'] = array_values(array_filter(
            $data['roles'],
            fn ($id) => (int) $id !== $superAdminId,
        ));
    }

    return $data;
}
```

For `CreateUser`, use `mutateFormDataBeforeCreate` instead (Filament's create/edit hook names differ):

```php
/**
 * @param  array<string, mixed>  $data
 * @return array<string, mixed>
 */
protected function mutateFormDataBeforeCreate(array $data): array
{
    // ...same body as above
}
```

- [ ] **Step 6: Run tests — confirm green**

```bash
php artisan test --compact --filter=UserResourceTest
```
Expected: ALL PASS (existing tests + 4 new).

If existing super_admin tests now fail with 403 because Shield permissions weren't seeded for super_admin: re-seed via `php artisan shield:super-admin --user=<admin_id>` or rely on the existing `RoleUserSeeder`. Verify the test `beforeEach` either seeds the permission or assigns `super_admin` (which has Shield's wildcard bypass).

#### Pint

- [ ] **Step 7: Format**

```bash
vendor/bin/pint --dirty --format agent
```

#### CR Loop

- [ ] **Step 8: Dispatch code reviewer**

Prompt:
> Review the new UserPolicy.php, the cleanup in UserResource.php, the Roles-select closure in UserForm, and the `mutateFormDataBeforeSave/Create` stripping. Verify: (a) the super_admin protection rule is enforced in BOTH `update` and `delete`, (b) the temporary canAccess override is removed, (c) the policy is auto-discovered by Laravel (User namespace + Policies/UserPolicy.php convention) — if not, an explicit `Gate::policy` registration in AuthServiceProvider is added, (d) **the super_admin role cannot be granted by a non-super_admin via ANY path**: not via the Select (filtered), not via Livewire state manipulation (stripped server-side in mutate hook). There must be a negative test proving the grant is rejected. (e) Tests cover positive AND negative cases. Reference forge-cms-overrides.md §3.3.

- [ ] **Step 9: Fix flagged issues** (loop).

- [ ] **Step 10: Re-dispatch CR until clean.**

#### Commit

- [ ] **Step 11: Run full suite**

```bash
php artisan test --compact
```
Expected: all green.

- [ ] **Step 12: Commit**

```bash
git add app/Policies/UserPolicy.php \
        app/Filament/Resources/Users/UserResource.php \
        app/Filament/Resources/Users/Schemas/UserForm.php \
        app/Filament/Resources/Users/Pages/CreateUser.php \
        app/Filament/Resources/Users/Pages/EditUser.php \
        tests/Feature/Admin/UserResourceTest.php
# Plus app/Providers/AuthServiceProvider.php if a manual policy registration was needed.
git commit -m "feat(admin): UserPolicy + role-escalation guards (policy, select filter, save-time strip)"
```

---

## Self-Review

- ✅ Spec §5.2 acceptance criteria all map to Tasks 2–5.
- ✅ Pre-flight Task 1 catches model assumptions (SoftDeletes).
- ✅ CR→FIX→TDD loop in every implementing task.
- ✅ Implementer subagents stop at Pint; main session commits.
- ✅ One commit per task = four commits (Tasks 2/3/4/5).
- ✅ Temporary `canAccess` override is explicitly removed in Task 5 — no orphan code.
- ✅ Test FQCNs match Filament 4 generated structure.
