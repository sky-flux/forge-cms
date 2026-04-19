# Dictionary (系统 → 字典) — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add an editable, cache-backed key/value lookup system (字典) so super_admins can manage runtime-configurable enums (e.g. external link channels, custom statuses) without code changes. Surfaces in Filament admin under 系统 → 字典.

**Architecture:** Two tables — `dictionary_types(code, name, remark)` parent and `dictionary_items(type_id, label, value, sort, is_default, status)` children. Filament `DictionaryTypeResource` with a nested `DictionaryItemsRelationManager` for item CRUD. A static helper `Dictionary::items('post_visibility')` returns a `Collection<DictionaryItem>` from `Cache::rememberForever`, busted on item save/delete via Eloquent model events.

**Tech Stack:** Laravel 13 Eloquent, Filament 5 (Resource + RelationManager), Spatie Permission 7, Filament Shield 4, Pest 4. Depends on Foundation plan having merged.

**Spec:** `docs/superpowers/specs/2026-04-19-system-admin-modules.md` §5.3

**Depends on:** `2026-04-19-system-foundation.md`. **Independent of** `2026-04-19-system-users.md` and `2026-04-19-system-settings.md` — can run in parallel with them.

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
| `database/migrations/<ts>_create_dictionary_types_table.php` | DDL | Create |
| `database/migrations/<ts>_create_dictionary_items_table.php` | DDL | Create |
| `app/Models/DictionaryType.php` | Eloquent model + items() hasMany | Create |
| `app/Models/DictionaryItem.php` | Eloquent model + type() belongsTo + cache-bust events | Create |
| `database/factories/DictionaryTypeFactory.php` | Factory | Create |
| `database/factories/DictionaryItemFactory.php` | Factory | Create |
| `database/seeders/DictionarySeeder.php` | Sample data (post_visibility, comment_status_label) | Create |
| `app/Support/Dictionary.php` | Static helper: `items()`, `value()`, `default()` | Create |
| `app/Filament/Resources/DictionaryTypes/DictionaryTypeResource.php` + Pages/Schemas/Tables | CRUD UI | Create (artisan) |
| `app/Filament/Resources/DictionaryTypes/RelationManagers/ItemsRelationManager.php` | Nested item CRUD | Create |
| `app/Policies/DictionaryTypePolicy.php` | Authorization | Create (Shield) |
| `tests/Feature/Admin/DictionaryTypeResourceTest.php` | Resource tests | Create |
| `tests/Feature/Support/DictionaryHelperTest.php` | Helper + cache tests | Create |

---

### Task 1: Pre-flight

**No commit.**

- [ ] **Step 1: Confirm Cache driver in test env**

```bash
grep -E '^CACHE_' .env .env.example .env.testing 2>/dev/null
php artisan config:show cache.default
```

Record the driver. Tests use the same driver (`array` is fine — `Cache::rememberForever` works there). If `database`, ensure `cache` table is migrated.

- [ ] **Step 2: Confirm migrations dir naming convention**

```bash
ls -1 database/migrations | head -5
```

The project uses `YYYY_MM_DD_HHMMSS_*.php`. `php artisan make:migration` will generate this automatically.

- [ ] **Step 3: Verify Foundation plan merged**

```bash
git log --oneline | grep '内容 and 系统 navigation groups'
```

If empty → STOP, Foundation must merge first.

---

### Task 2: Migrations + models + factories

**Files:**
- Create: `database/migrations/<ts>_create_dictionary_types_table.php`
- Create: `database/migrations/<ts>_create_dictionary_items_table.php`
- Create: `app/Models/DictionaryType.php`, `app/Models/DictionaryItem.php`
- Create: `database/factories/DictionaryTypeFactory.php`, `DictionaryItemFactory.php`
- Create: `tests/Unit/Models/DictionaryRelationsTest.php`

#### TDD

- [ ] **Step 1: Generate scaffolding**

```bash
php artisan make:model DictionaryType -mf --no-interaction
php artisan make:model DictionaryItem -mf --no-interaction
```

- [ ] **Step 2: Write the failing test**

Create `tests/Unit/Models/DictionaryRelationsTest.php`:

```php
<?php

declare(strict_types=1);

use App\Models\DictionaryItem;
use App\Models\DictionaryType;

test('a dictionary type has many items ordered by sort then id', function (): void {
    $type = DictionaryType::factory()->create(['code' => 'demo']);
    $b = DictionaryItem::factory()->for($type, 'type')->create(['sort' => 20, 'label' => 'B']);
    $a = DictionaryItem::factory()->for($type, 'type')->create(['sort' => 10, 'label' => 'A']);

    expect($type->items()->pluck('label')->all())->toBe(['A', 'B']);
});

test('a dictionary type code is unique', function (): void {
    DictionaryType::factory()->create(['code' => 'duplicate']);

    expect(fn () => DictionaryType::factory()->create(['code' => 'duplicate']))
        ->toThrow(\Illuminate\Database\QueryException::class);
});

test('deleting a type cascades to its items', function (): void {
    $type = DictionaryType::factory()->create();
    DictionaryItem::factory()->count(3)->for($type, 'type')->create();

    $type->delete();

    expect(DictionaryItem::where('type_id', $type->id)->count())->toBe(0);
});
```

- [ ] **Step 3: Run tests — confirm red**

```bash
php artisan test --compact --filter=DictionaryRelationsTest
```
Expected: FAIL — tables/models not yet wired.

- [ ] **Step 4: Implement migrations**

Edit the generated `*_create_dictionary_types_table.php`:

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dictionary_types', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 64)->unique();
            $table->string('name', 128);
            $table->string('remark', 255)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dictionary_types');
    }
};
```

Edit `*_create_dictionary_items_table.php`:

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dictionary_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('type_id')
                ->constrained('dictionary_types')
                ->cascadeOnDelete();
            $table->string('label', 128);
            $table->string('value', 128);
            $table->unsignedInteger('sort')->default(0);
            $table->boolean('is_default')->default(false);
            $table->boolean('status')->default(true); // true = enabled
            $table->timestamps();

            $table->unique(['type_id', 'value']);
            $table->index(['type_id', 'sort']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dictionary_items');
    }
};
```

- [ ] **Step 5: Implement models**

`app/Models/DictionaryType.php`:

```php
<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\DictionaryTypeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DictionaryType extends Model
{
    /** @use HasFactory<DictionaryTypeFactory> */
    use HasFactory;

    protected $fillable = ['code', 'name', 'remark'];

    public function items(): HasMany
    {
        return $this->hasMany(DictionaryItem::class, 'type_id')
            ->orderBy('sort')
            ->orderBy('id');
    }
}
```

`app/Models/DictionaryItem.php`:

```php
<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\DictionaryItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DictionaryItem extends Model
{
    /** @use HasFactory<DictionaryItemFactory> */
    use HasFactory;

    protected $fillable = ['type_id', 'label', 'value', 'sort', 'is_default', 'status'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort' => 'integer',
            'is_default' => 'boolean',
            'status' => 'boolean',
        ];
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(DictionaryType::class, 'type_id');
    }
}
```

- [ ] **Step 6: Implement factories**

`database/factories/DictionaryTypeFactory.php`:

```php
<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\DictionaryType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DictionaryType>
 */
class DictionaryTypeFactory extends Factory
{
    protected $model = DictionaryType::class;

    public function definition(): array
    {
        return [
            'code' => fake()->unique()->slug(2),
            'name' => fake()->words(2, true),
            'remark' => fake()->optional()->sentence(),
        ];
    }
}
```

`database/factories/DictionaryItemFactory.php`:

```php
<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\DictionaryItem;
use App\Models\DictionaryType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DictionaryItem>
 */
class DictionaryItemFactory extends Factory
{
    protected $model = DictionaryItem::class;

    public function definition(): array
    {
        return [
            'type_id' => DictionaryType::factory(),
            'label' => fake()->word(),
            'value' => fake()->unique()->slug(1),
            'sort' => fake()->numberBetween(0, 100),
            'is_default' => false,
            'status' => true,
        ];
    }
}
```

- [ ] **Step 7: Migrate test DB and run tests — confirm green**

```bash
php artisan migrate --env=testing --no-interaction
php artisan test --compact --filter=DictionaryRelationsTest
```

If the project uses `LazilyRefreshDatabase` / `RefreshDatabase` in `tests/TestCase.php`, the migration runs automatically during the test — no manual step needed. Verify by inspecting `tests/Pest.php`.

Expected: 3 PASS.

#### Pint

- [ ] **Step 8: Format**

```bash
vendor/bin/pint --dirty --format agent
```

#### CR Loop

- [ ] **Step 9: Dispatch code reviewer**

Prompt:
> Review unstaged diff: 2 migrations, 2 models, 2 factories, 1 test file for the Dictionary feature. Verify: (a) migrations are reversible, indexes match `(type_id, sort)` query patterns, unique on `(type_id, value)` is correct, (b) models use `casts()` method (not `$casts` property), `$fillable` property form matches sibling content models, (c) factories use `fake()` not `$this->faker` (project convention check — verify against `database/factories/UserFactory.php`), (d) `items()` relation default order is `sort,id` so admin UX is deterministic. Reference forge-cms-overrides.md §2.

- [ ] **Step 10: Fix flagged issues** (loop with tests + pint).

- [ ] **Step 11: Re-dispatch CR until clean.**

#### Commit

- [ ] **Step 12: Commit**

```bash
git add database/migrations/*_create_dictionary_types_table.php \
        database/migrations/*_create_dictionary_items_table.php \
        app/Models/DictionaryType.php \
        app/Models/DictionaryItem.php \
        database/factories/DictionaryTypeFactory.php \
        database/factories/DictionaryItemFactory.php \
        tests/Unit/Models/DictionaryRelationsTest.php
git commit -m "feat(dictionary): add types/items tables, models, and factories"
```

---

### Task 3: DictionaryTypeResource (Filament CRUD)

**Files:**
- Create (via artisan): `app/Filament/Resources/DictionaryTypes/{DictionaryTypeResource.php, Pages/*, Schemas/DictionaryTypeForm.php, Tables/DictionaryTypesTable.php}`
- Create: `tests/Feature/Admin/DictionaryTypeResourceTest.php`

#### TDD

- [ ] **Step 1: Generate scaffold**

```bash
php artisan make:filament-resource DictionaryType --generate --no-interaction
```

- [ ] **Step 2: Configure nav group + labels**

In `DictionaryTypeResource.php`, add:

```php
protected static string|\UnitEnum|null $navigationGroup = '系统';

protected static ?int $navigationSort = 3;

protected static ?string $navigationLabel = '字典';

protected static ?string $modelLabel = '字典类型';

protected static ?string $pluralModelLabel = '字典类型';
```

- [ ] **Step 3: Write the failing test**

Create `tests/Feature/Admin/DictionaryTypeResourceTest.php`:

```php
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
```

- [ ] **Step 4: Run tests — confirm red**

```bash
php artisan test --compact --filter=DictionaryTypeResourceTest
```
Expected: most fail (form lacks unique rule, may lack required, etc.).

- [ ] **Step 5: Implement DictionaryTypeForm**

Replace `app/Filament/Resources/DictionaryTypes/Schemas/DictionaryTypeForm.php` body:

```php
<?php

declare(strict_types=1);

namespace App\Filament\Resources\DictionaryTypes\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class DictionaryTypeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('code')
                ->required()
                ->maxLength(64)
                ->alphaDash()
                ->unique(ignoreRecord: true)
                ->helperText('Identifier used in code, e.g. post_visibility. Cannot be changed casually.'),

            TextInput::make('name')
                ->required()
                ->maxLength(128),

            Textarea::make('remark')
                ->maxLength(255)
                ->rows(2),
        ]);
    }
}
```

- [ ] **Step 6: Implement DictionaryTypesTable**

Replace `app/Filament/Resources/DictionaryTypes/Tables/DictionaryTypesTable.php`:

```php
<?php

declare(strict_types=1);

namespace App\Filament\Resources\DictionaryTypes\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class DictionaryTypesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')->searchable()->sortable(),
                TextColumn::make('name')->searchable(),
                TextColumn::make('items_count')
                    ->counts('items')
                    ->label('Items'),
                TextColumn::make('updated_at')->dateTime()->sortable(),
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

- [ ] **Step 7: Run tests — confirm green**

```bash
php artisan test --compact --filter=DictionaryTypeResourceTest
```
Expected: all PASS.

#### Pint

- [ ] **Step 8: Format**

```bash
vendor/bin/pint --dirty --format agent
```

#### CR Loop

- [ ] **Step 9: Dispatch code reviewer**

Prompt:
> Review the new DictionaryTypeResource scaffold + form/table customization. Verify: (a) navigation group/sort/labels per spec, (b) form's `code` uses alphaDash + unique, (c) table uses `counts('items')` to avoid N+1 (preventLazyLoading is on), (d) tests use sibling-style `test()` + `Livewire::actingAs()`. Reference forge-cms-overrides.md.

- [ ] **Step 10: Fix flagged issues** (loop).

- [ ] **Step 11: Re-dispatch CR until clean.**

#### Commit

- [ ] **Step 12: Commit**

```bash
git add app/Filament/Resources/DictionaryTypes tests/Feature/Admin/DictionaryTypeResourceTest.php
git commit -m "feat(dictionary): DictionaryTypeResource with unique-code enforcement"
```

---

### Task 4: ItemsRelationManager (nested item CRUD)

**Files:**
- Create: `app/Filament/Resources/DictionaryTypes/RelationManagers/ItemsRelationManager.php`
- Modify: `app/Filament/Resources/DictionaryTypes/DictionaryTypeResource.php` (register the relation manager)
- Modify: `tests/Feature/Admin/DictionaryTypeResourceTest.php`

#### TDD

- [ ] **Step 1: Generate the relation manager**

```bash
php artisan make:filament-relation-manager DictionaryTypeResource items label --no-interaction
```

This generates `app/Filament/Resources/DictionaryTypes/RelationManagers/ItemsRelationManager.php`. Inspect the generated file.

- [ ] **Step 2: Register the relation manager**

In `DictionaryTypeResource.php`, replace `getRelations()`:

```php
public static function getRelations(): array
{
    return [
        \App\Filament\Resources\DictionaryTypes\RelationManagers\ItemsRelationManager::class,
    ];
}
```

- [ ] **Step 3: Write the failing tests**

Append to `tests/Feature/Admin/DictionaryTypeResourceTest.php`:

```php
use App\Models\DictionaryItem;
use App\Filament\Resources\DictionaryTypes\Pages\EditDictionaryType;
use App\Filament\Resources\DictionaryTypes\RelationManagers\ItemsRelationManager;

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
```

- [ ] **Step 4: Run tests — confirm red**

```bash
php artisan test --compact --filter='items relation|item value must be unique'
```
Expected: FAIL — generated form lacks all the columns we need + the per-type uniqueness rule.

- [ ] **Step 5: Implement ItemsRelationManager**

Replace the generated form/table in `ItemsRelationManager.php`:

```php
<?php

declare(strict_types=1);

namespace App\Filament\Resources\DictionaryTypes\RelationManagers;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Validation\Rule;

class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('label')
                ->required()
                ->maxLength(128),

            TextInput::make('value')
                ->required()
                ->maxLength(128)
                ->rule(fn () => Rule::unique('dictionary_items', 'value')
                    ->where('type_id', $this->getOwnerRecord()->id)
                    ->ignore($this->getMountedTableActionRecord()?->id)),

            TextInput::make('sort')
                ->numeric()
                ->default(0),

            Toggle::make('is_default')->default(false),
            Toggle::make('status')->default(true)->label('Enabled'),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('label')
            ->columns([
                TextColumn::make('label')->sortable()->searchable(),
                TextColumn::make('value')->sortable()->searchable(),
                TextColumn::make('sort')->sortable(),
                IconColumn::make('is_default')->boolean(),
                IconColumn::make('status')->boolean()->label('Enabled'),
            ])
            ->headerActions([
                CreateAction::make(),
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
            ->defaultSort('sort');
    }
}
```

- [ ] **Step 6: Run tests — confirm green**

```bash
php artisan test --compact --filter=DictionaryTypeResourceTest
```
Expected: all PASS.

#### Pint

- [ ] **Step 7: Format**

```bash
vendor/bin/pint --dirty --format agent
```

#### CR Loop

- [ ] **Step 8: Dispatch code reviewer**

Prompt:
> Review the new ItemsRelationManager. Verify: (a) per-type uniqueness rule on `value` works for both create and edit (note the `ignore()` for edit), (b) defaults sort by `sort`, (c) all columns/actions match the sibling Filament 5 patterns from PostsTable, (d) `getMountedTableActionRecord()` is the correct accessor for the currently-edited record. If a different accessor is canonical in Filament 5, flag it.

- [ ] **Step 9: Fix flagged issues** (loop).

- [ ] **Step 10: Re-dispatch CR until clean.**

#### Commit

- [ ] **Step 11: Commit**

```bash
git add app/Filament/Resources/DictionaryTypes/RelationManagers/ItemsRelationManager.php \
        app/Filament/Resources/DictionaryTypes/DictionaryTypeResource.php \
        tests/Feature/Admin/DictionaryTypeResourceTest.php
git commit -m "feat(dictionary): ItemsRelationManager with per-type unique value rule"
```

---

### Task 5: `Dictionary` helper + cache + Shield policies

**Files:**
- Create: `app/Support/Dictionary.php`
- Modify: `app/Models/DictionaryItem.php` (booted events to bust cache)
- Create: `database/seeders/DictionarySeeder.php` (sample data)
- Create: `app/Policies/DictionaryTypePolicy.php` (via Shield)
- Create: `tests/Feature/Support/DictionaryHelperTest.php`

#### TDD

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/Support/DictionaryHelperTest.php`:

```php
<?php

declare(strict_types=1);

use App\Models\DictionaryItem;
use App\Models\DictionaryType;
use App\Support\Dictionary;
use Illuminate\Support\Facades\Cache;

test('items() returns enabled items for a type ordered by sort', function (): void {
    $type = DictionaryType::factory()->create(['code' => 'priority']);
    DictionaryItem::factory()->for($type, 'type')->create(['label' => 'Low',  'value' => 'low',  'sort' => 30, 'status' => true]);
    DictionaryItem::factory()->for($type, 'type')->create(['label' => 'High', 'value' => 'high', 'sort' => 10, 'status' => true]);
    DictionaryItem::factory()->for($type, 'type')->create(['label' => 'Off',  'value' => 'off',  'sort' => 20, 'status' => false]);

    $items = Dictionary::items('priority');

    expect($items->pluck('value')->all())->toBe(['high', 'low']);
});

test('items() returns an empty collection for an unknown code', function (): void {
    expect(Dictionary::items('does-not-exist')->all())->toBe([]);
});

test('items() result is cached and bust on item save', function (): void {
    Cache::flush();
    $type = DictionaryType::factory()->create(['code' => 'channel']);
    DictionaryItem::factory()->for($type, 'type')->create(['value' => 'email', 'status' => true]);

    Dictionary::items('channel'); // populates cache
    expect(Cache::has('dict.items.channel'))->toBeTrue();

    DictionaryItem::factory()->for($type, 'type')->create(['value' => 'sms', 'status' => true]);

    expect(Cache::has('dict.items.channel'))->toBeFalse()
        ->and(Dictionary::items('channel')->pluck('value')->sort()->values()->all())->toBe(['email', 'sms']);
});

test('default() returns the first item with is_default=true, or null', function (): void {
    $type = DictionaryType::factory()->create(['code' => 'visibility']);
    DictionaryItem::factory()->for($type, 'type')->create(['value' => 'public',  'is_default' => true,  'status' => true]);
    DictionaryItem::factory()->for($type, 'type')->create(['value' => 'private', 'is_default' => false, 'status' => true]);

    expect(Dictionary::default('visibility')?->value)->toBe('public')
        ->and(Dictionary::default('does-not-exist'))->toBeNull();
});
```

- [ ] **Step 2: Run tests — confirm red**

```bash
php artisan test --compact --filter=DictionaryHelperTest
```
Expected: FAIL — `App\Support\Dictionary` doesn't exist.

- [ ] **Step 3: Implement the helper**

Create `app/Support/Dictionary.php`:

```php
<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\DictionaryItem;
use App\Models\DictionaryType;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

final class Dictionary
{
    public const string CACHE_PREFIX = 'dict.items.';

    /**
     * Enabled items for the given type, ordered by sort then id.
     *
     * @return Collection<int, DictionaryItem>
     */
    public static function items(string $code): Collection
    {
        return Cache::rememberForever(
            self::cacheKey($code),
            fn (): Collection => DictionaryType::query()
                ->where('code', $code)
                ->first()
                ?->items()
                ->where('status', true)
                ->get() ?? collect(),
        );
    }

    public static function default(string $code): ?DictionaryItem
    {
        return self::items($code)->firstWhere('is_default', true);
    }

    public static function cacheKey(string $code): string
    {
        return self::CACHE_PREFIX . $code;
    }

    public static function forget(string $code): void
    {
        Cache::forget(self::cacheKey($code));
    }
}
```

- [ ] **Step 4: Wire cache invalidation on `DictionaryItem`**

Edit `app/Models/DictionaryItem.php` — add a `booted()` method:

```php
protected static function booted(): void
{
    $bust = function (DictionaryItem $item): void {
        $code = $item->type()->value('code');
        if ($code !== null) {
            \App\Support\Dictionary::forget($code);
        }
    };

    static::saved($bust);
    static::deleted($bust);
}
```

(Use `->value('code')` instead of `->first()->code` to avoid loading the whole parent and to side-step preventLazyLoading.)

- [ ] **Step 5: Run tests — confirm green**

```bash
php artisan test --compact --filter=DictionaryHelperTest
```
Expected: all PASS.

- [ ] **Step 6: Generate Shield policy**

```bash
php artisan shield:generate --resource=DictionaryTypeResource --no-interaction
```

This creates `app/Policies/DictionaryTypePolicy.php` and the permission set. Verify by running:

```bash
php artisan test --compact --filter=DictionaryTypeResourceTest
```
Existing tests must still pass. If the resource now 403s for super_admin, ensure the test seeds `super_admin` role (it does in `beforeEach`).

- [ ] **Step 7: Sample seeder (optional but recommended)**

Create `database/seeders/DictionarySeeder.php`:

```php
<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\DictionaryItem;
use App\Models\DictionaryType;
use Illuminate\Database\Seeder;

class DictionarySeeder extends Seeder
{
    public function run(): void
    {
        $visibility = DictionaryType::firstOrCreate(
            ['code' => 'post_visibility'],
            ['name' => 'Post Visibility', 'remark' => 'Who can see a post'],
        );

        foreach ([
            ['label' => 'Public',     'value' => 'public',     'sort' => 10, 'is_default' => true],
            ['label' => 'Members',    'value' => 'members',    'sort' => 20, 'is_default' => false],
            ['label' => 'Private',    'value' => 'private',    'sort' => 30, 'is_default' => false],
        ] as $row) {
            DictionaryItem::firstOrCreate(
                ['type_id' => $visibility->id, 'value' => $row['value']],
                $row + ['status' => true],
            );
        }
    }
}
```

Register it in `database/seeders/DatabaseSeeder.php`'s `run()`:
```php
$this->call(DictionarySeeder::class);
```

#### Pint

- [ ] **Step 8: Format**

```bash
vendor/bin/pint --dirty --format agent
```

#### CR Loop

- [ ] **Step 9: Dispatch code reviewer**

Prompt:
> Review unstaged diff: `app/Support/Dictionary.php`, the `booted()` cache-bust on DictionaryItem, the Shield-generated DictionaryTypePolicy, the DictionarySeeder. Verify: (a) the helper is Octane-safe (no static state, closure-based cache), (b) `value('code')` avoids triggering preventLazyLoading on the parent, (c) `Cache::rememberForever` is correct for a low-write/high-read lookup table, (d) seeder uses `firstOrCreate` so it's idempotent. Reference forge-cms-overrides.md §1.

- [ ] **Step 10: Fix flagged issues** (loop).

- [ ] **Step 11: Re-dispatch CR until clean.**

#### Commit

- [ ] **Step 12: Run full suite**

```bash
php artisan test --compact
```
Expected: all green.

- [ ] **Step 13: Commit**

```bash
git add app/Support/Dictionary.php \
        app/Models/DictionaryItem.php \
        app/Policies/DictionaryTypePolicy.php \
        database/seeders/DictionarySeeder.php \
        database/seeders/DatabaseSeeder.php \
        tests/Feature/Support/DictionaryHelperTest.php
git commit -m "feat(dictionary): cached helper, cache-bust events, Shield policy, sample seeder"
```

---

## Self-Review

- ✅ Spec §5.3 acceptance criteria all map to Tasks 2–5.
- ✅ CR→FIX→TDD loop in every implementing task.
- ✅ Implementer subagents end at Pint; main session commits.
- ✅ One commit per task = four commits (Tasks 2/3/4/5).
- ✅ Cache-bust uses `->value('code')` not eager-load chain — Octane-safe and lazy-load-violation-safe.
- ✅ Per-type uniqueness on item value is enforced both in DB (unique index) and form rule.
- ✅ Test FQCNs match the artisan-generated split-file layout.
