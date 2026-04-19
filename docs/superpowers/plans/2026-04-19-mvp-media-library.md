# MVP Media Library — Implementation Plan

> REQUIRED SUB-SKILL: superpowers:subagent-driven-development. 1 task = 1 commit. Implementer never commits.

**Worktree:** `.worktrees/mvp-media-library` on `feat/mvp-media-library` from main.
**Stack:** Laravel 13, Filament 5.5.2, spatie/laravel-medialibrary v11, Pest 4.
**Spec:** `docs/superpowers/specs/2026-04-19-mvp-completion-batch-2.md` § Worktree C.

## Workflow per Task

TDD → Pint → combined CR → fix loop → controller commits.

## Conventions (strict)

- `declare(strict_types=1);` every PHP file
- Filament 5 split-file layout: `Media/MediaResource.php` + `Pages/{List,Create,Edit}Media.php` + `Schemas/MediaForm.php` + `Tables/MediaTable.php`
- Nav: `$navigationGroup = '内容'`, `$navigationSort = 6`, `$navigationLabel = '媒体'`, `$modelLabel = '媒体'`, `$pluralModelLabel = '媒体'`
- Pest test pattern: `beforeEach(fn () => $this->withoutVite())` + `Role::findOrCreate('super_admin')`
- Use `Spatie\MediaLibrary\MediaCollections\Models\Media` as the Filament model (it already exists, no migration needed)
- Shield policy via `php artisan shield:generate --resource=MediaResource --option=policies_and_permissions --panel=admin --no-interaction` (NOT plain `shield:generate` — requires panel + option)
- Pint: `vendor/bin/pint --dirty --format agent`
- Tests: `php artisan test --compact --filter=MediaResourceTest`

## Pre-flight

```bash
cd /Users/martinadamsdev/workspace/forge-cms/.worktrees/mvp-media-library

# Confirm Media model + media table exist
php artisan tinker --execute 'echo \Spatie\MediaLibrary\MediaCollections\Models\Media::class;'
cat database/migrations/*_create_media_table.php | head -30

# Sibling pattern
ls app/Filament/Resources/Categories
cat app/Filament/Resources/Categories/CategoryResource.php
cat app/Filament/Resources/Categories/Tables/CategoriesTable.php
ls app/Policies
cat app/Policies/CategoryPolicy.php 2>/dev/null | head -40

# Confirm Shield's panel flag
php artisan shield:generate --help | grep -E 'option|panel' | head
```

---

### Task 1 — MediaResource scaffold + CRUD + tests + policy

**Files:**
- Create: `app/Filament/Resources/Media/MediaResource.php` + Pages/ + Schemas/ + Tables/
- Create: `app/Policies/MediaPolicy.php` (via Shield)
- Create: `tests/Feature/Admin/MediaResourceTest.php`

#### TDD

**Step 1 — failing tests.** Create `tests/Feature/Admin/MediaResourceTest.php`:

```php
<?php

declare(strict_types=1);

use App\Filament\Resources\Media\MediaResource;
use App\Filament\Resources\Media\Pages\ListMedia;
use App\Models\Post;
use App\Models\User;
use Livewire\Livewire;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    $this->withoutVite();
    Role::findOrCreate('super_admin');
});

test('MediaResource binds to Media model under 内容', function (): void {
    expect(MediaResource::getModel())->toBe(Media::class)
        ->and(MediaResource::getNavigationGroup())->toBe('内容')
        ->and(MediaResource::getNavigationSort())->toBe(6);
});

test('super_admin can list media', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');

    $this->actingAs($admin)
        ->get(MediaResource::getUrl('index'))
        ->assertSuccessful();
});

test('guests are redirected from media index', function (): void {
    $this->get(MediaResource::getUrl('index'))->assertRedirect('/console/login');
});

test('super_admin can delete a media row via resource', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');

    $post = Post::factory()->create();
    $post->addMediaFromString('fake-binary-contents')
        ->usingFileName('test.txt')
        ->usingName('test')
        ->toMediaCollection();

    $media = $post->getFirstMedia();
    expect($media)->not->toBeNull();

    Livewire::actingAs($admin)
        ->test(ListMedia::class)
        ->callTableAction('delete', $media);

    expect(Media::find($media->id))->toBeNull();
});
```

**Step 2.** Run — RED.

**Step 3 — scaffold.** Run (DB_HOST override needed for --generate introspection):

```bash
DB_HOST=127.0.0.1 php artisan make:filament-resource Media --model-namespace='Spatie\MediaLibrary\MediaCollections\Models' --no-interaction
```

If `--model-namespace` isn't supported in this Filament version, create the files manually — see sibling CategoryResource structure.

**Step 4 — configure MediaResource.** Edit `app/Filament/Resources/Media/MediaResource.php`:

```php
protected static ?string $model = \Spatie\MediaLibrary\MediaCollections\Models\Media::class;

protected static string|\UnitEnum|null $navigationGroup = '内容';

protected static ?int $navigationSort = 6;

protected static ?string $navigationLabel = '媒体';

protected static ?string $modelLabel = '媒体';

protected static ?string $pluralModelLabel = '媒体';

protected static string|\BackedEnum|null $navigationIcon = \Filament\Support\Icons\Heroicon::OutlinedPhoto;

// Temporary before Shield policy
public static function canAccess(): bool { return true; }
```

**Step 5 — MediaTable.** Replace `app/Filament/Resources/Media/Tables/MediaTable.php`:

```php
<?php

declare(strict_types=1);

namespace App\Filament\Resources\Media\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class MediaTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('preview')
                    ->label('Preview')
                    ->state(fn ($record) => $record->getFullUrl())
                    ->square(),
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('collection_name')->label('Collection')->sortable(),
                TextColumn::make('mime_type')->label('Mime')->sortable(),
                TextColumn::make('size')
                    ->formatStateUsing(fn (int $state): string => round($state / 1024, 1).' KB')
                    ->sortable(),
                TextColumn::make('model_type')->label('Attached to')->sortable(),
                TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->filters([
                SelectFilter::make('mime_type')
                    ->label('Mime category')
                    ->options([
                        'image/' => 'Images',
                        'video/' => 'Videos',
                        'application/pdf' => 'PDFs',
                    ])
                    ->query(fn ($query, array $data) => $data['value']
                        ? $query->where('mime_type', 'like', $data['value'].'%')
                        : $query),
                SelectFilter::make('collection_name')
                    ->label('Collection')
                    ->options(fn () => \Spatie\MediaLibrary\MediaCollections\Models\Media::query()
                        ->distinct()
                        ->pluck('collection_name', 'collection_name')
                        ->toArray()),
            ])
            ->recordActions([
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
```

**Step 6 — MediaForm (read-mostly).** Replace `Schemas/MediaForm.php` with a simple name field (since Media is mostly managed via upload, not form-edit):

```php
<?php

declare(strict_types=1);

namespace App\Filament\Resources\Media\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class MediaForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->required()->maxLength(255),
            TextInput::make('collection_name')->disabled(),
            TextInput::make('mime_type')->disabled(),
            TextInput::make('model_type')->disabled(),
        ]);
    }
}
```

**Step 7 — Pages: remove CreateMedia from getPages() if present.** Media should NOT be created via admin — it's uploaded via Post/Page forms. In `MediaResource.php::getPages()`:

```php
public static function getPages(): array
{
    return [
        'index' => Pages\ListMedia::route('/'),
        'edit' => Pages\EditMedia::route('/{record}/edit'),
    ];
}
```

Delete `Pages/CreateMedia.php` (if generated).

**Step 8 — run tests.** Expect GREEN. If the delete test fails, check Livewire's `callTableAction` signature in Filament 5 (might need `callTableAction('delete', record: $media)` explicit keyword).

**Step 9 — Shield policy.**

```bash
DB_HOST=127.0.0.1 php artisan shield:generate --resource=MediaResource --option=policies_and_permissions --panel=admin --no-interaction
```

Inspect `app/Policies/MediaPolicy.php`; if generated with role-based checks via `$user->hasAnyRole([...])` (matches sibling PostPolicy style), keep as-is. Then remove the temporary `canAccess` override from MediaResource.

Re-run tests — should still be GREEN.

**Step 10 — Pint.**

**Step 11 — commit.** Message:
```
feat(admin): MediaResource under 内容 for browsing spatie/medialibrary media
```

---

## Self-Review

- Single task on `feat/mvp-media-library`
- Tests cover: model binding, nav group, index access, guest redirect, delete action
- No migrations introduced (Media table already exists)
- Shield policy generated + temp canAccess removed
- Policy style matches sibling PostPolicy (role-based)
- Filament 5 split-file layout matches sibling CategoryResource

## Acceptance (merged)

- `/console/media` lists all uploaded media
- super_admin can delete individual media rows or bulk delete
- Guest/non-admin redirected/forbidden
- Mime-category filter works
