# Settings Page (系统 → 配置) — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a single, super_admin-only Filament Page (`系统 → 配置`) backed by `spatie/laravel-settings` so site-wide values (site name, default SEO description, contact email, default OG image) are editable in the UI and resolvable anywhere via DI.

**Architecture:** `spatie/laravel-settings` provides typed setting classes persisted to a `settings` table via a settings migration. A custom Filament Page (`SystemSettings`) hydrates a `GeneralSettings` instance into a Form schema on mount and persists on save. Single-record by design (no Resource), no tabs in v1 (single group). Authorization via a Page-level `canAccess()` calling `Gate::allows('manage_settings')` — a Shield-style permission seeded into the `super_admin` role.

**Tech Stack:** Laravel 13, Filament 5 Pages, `spatie/laravel-settings` v3+, Pest 4. Depends on Foundation plan having merged.

**Spec:** `docs/superpowers/specs/2026-04-19-system-admin-modules.md` §5.4

**Depends on:** `2026-04-19-system-foundation.md`. **Independent of** the Users and Dictionary plans — can run in parallel.

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
| `composer.json` / `composer.lock` | Add `spatie/laravel-settings` | Modify (composer require) |
| `config/settings.php` | Settings package config | Create (vendor:publish) |
| `database/migrations/<ts>_create_settings_table.php` | Settings DDL | Create (vendor:publish) |
| `database/settings/<ts>_create_general_settings.php` | Initial GeneralSettings values | Create |
| `app/Settings/GeneralSettings.php` | Typed settings class | Create |
| `app/Filament/Pages/SystemSettings.php` | Filament Page (form schema + save) | Create |
| `resources/views/filament/pages/system-settings.blade.php` | Page view (schema render) | Create (or use built-in) |
| `database/seeders/SettingsPermissionSeeder.php` | Seeds `manage_settings` permission to super_admin | Create |
| `tests/Feature/Admin/SystemSettingsPageTest.php` | Pest tests | Create |

---

### Task 1: Pre-flight — verify package compatibility

**No commit.**

- [ ] **Step 1: Search docs and confirm v4 Filament integration story**

Use Boost MCP `search-docs`:
```
queries=["spatie laravel settings install", "spatie laravel settings filament", "filament page form schema mount save"]
```

- [ ] **Step 2: Confirm Filament 5 Page API**

Inspect a sibling Page if any exists. Currently `app/Filament/Pages/` does not exist. Create a quick reference by inspecting the vendor Dashboard:
```bash
grep -rn "extends Page" vendor/filament/filament/src/Pages | head -5
```

Record which Page class to extend and how to attach a form schema (`HasSchemas` concern in v4).

- [ ] **Step 3: Verify spatie/laravel-settings supports Laravel 13**

```bash
composer why-not spatie/laravel-settings:^3.0
```

If incompatible with installed Laravel/PHP, record:
> Package `spatie/laravel-settings` is incompatible. Falling back to hand-rolled approach: a single `Setting` Eloquent model with `key/value` rows + a `Settings::get('site_name')` helper. This adds ~150 lines but no new dependency.

The rest of this plan is written for the package path; if falling back, Task 2 substitutes a hand-rolled `app/Models/Setting.php` + helper, and Task 3 substitutes a plain DTO instead of a settings class. Tasks 4–5 are unchanged.

- [ ] **Step 4: Verify `Settings::toArray()` is available in the installed version**

After the install step in Task 2 completes, run:
```bash
grep -n "function toArray\|implements .*Arrayable" vendor/spatie/laravel-settings/src/Settings.php
```

- **Match found → Task 4 Step 3 can use `app(GeneralSettings::class)->toArray()` as written.**
- **No match →** in Task 4 Step 3, replace `->toArray()` with:
  ```php
  $this->form->fill(collect(array_keys(get_object_vars($settings = app(GeneralSettings::class))))
      ->mapWithKeys(fn (string $k) => [$k => $settings->{$k}])
      ->all());
  ```
  This reads only the declared typed properties and avoids any hidden parent-class fields.

Record the chosen path in this plan as `> toArray verification: available` or `> toArray verification: use get_object_vars fallback`.

- [ ] **Step 5: Verify Foundation plan merged**

```bash
git log --oneline | grep '内容 and 系统 navigation groups'
```

Empty → STOP.

---

### Task 2: Install + publish spatie/laravel-settings

**Files:**
- Modify: `composer.json`, `composer.lock`
- Create: `config/settings.php`
- Create: `database/migrations/<ts>_create_settings_table.php`

#### TDD

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Admin/SystemSettingsPageTest.php` (only the migration-related test for this task):

```php
<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;

test('settings table exists after install', function (): void {
    expect(Schema::hasTable('settings'))->toBeTrue()
        ->and(Schema::hasColumns('settings', ['group', 'name', 'payload', 'locked']))->toBeTrue();
});
```

- [ ] **Step 2: Run — confirm red**

```bash
php artisan test --compact --filter='settings table exists'
```
Expected: FAIL — table not present.

- [ ] **Step 3: Install package**

```bash
composer require spatie/laravel-settings
```

- [ ] **Step 4: Publish migrations + config**

```bash
php artisan vendor:publish --provider="Spatie\LaravelSettings\LaravelSettingsServiceProvider" --tag="migrations"
php artisan vendor:publish --provider="Spatie\LaravelSettings\LaravelSettingsServiceProvider" --tag="settings"
php artisan migrate --no-interaction
```

- [ ] **Step 5: Run — confirm green**

```bash
php artisan test --compact --filter='settings table exists'
```
Expected: PASS.

#### Pint

- [ ] **Step 6: Format**

```bash
vendor/bin/pint --dirty --format agent
```

#### CR Loop

- [ ] **Step 7: Dispatch code reviewer**

Prompt:
> Review unstaged diff for the spatie/laravel-settings install: composer.json/lock, config/settings.php, the published migration, and the schema-existence test. Verify: (a) installed version is compatible with the project's Laravel 13 + PHP 8.5, (b) config/settings.php is unmodified-from-vendor or has only justified diffs, (c) the migration is applied and reversible, (d) no other vendor:publish artifacts were committed accidentally.

- [ ] **Step 8: Fix flagged issues** (loop with tests + pint).

- [ ] **Step 9: Re-dispatch CR until clean.**

#### Commit

- [ ] **Step 10: Commit**

```bash
git add composer.json composer.lock config/settings.php \
        database/migrations/*_create_settings_table.php \
        tests/Feature/Admin/SystemSettingsPageTest.php
git commit -m "chore(deps): install spatie/laravel-settings + publish settings table"
```

---

### Task 3: GeneralSettings class + initial values migration

**Files:**
- Create: `app/Settings/GeneralSettings.php`
- Create: `database/settings/<ts>_create_general_settings.php`
- Modify: `config/settings.php` (register the settings class + migration path)
- Modify: `tests/Feature/Admin/SystemSettingsPageTest.php`

#### TDD

- [ ] **Step 1: Write the failing tests**

Append to `tests/Feature/Admin/SystemSettingsPageTest.php`:

```php
use App\Settings\GeneralSettings;

test('GeneralSettings is resolvable from the container with defaults', function (): void {
    $settings = app(GeneralSettings::class);

    expect($settings->site_name)->toBeString()->not->toBeEmpty()
        ->and($settings->site_description)->toBeString()
        ->and($settings->contact_email)->toBeString()
        ->and($settings->default_seo_description)->toBeString()
        ->and($settings->default_og_image)->toBeNull(); // optional, default null
});

test('writing to GeneralSettings persists across resolves', function (): void {
    $settings = app(GeneralSettings::class);
    $settings->site_name = 'New Forge Brand';
    $settings->save();

    app()->forgetInstance(GeneralSettings::class);

    expect(app(GeneralSettings::class)->site_name)->toBe('New Forge Brand');
});
```

- [ ] **Step 2: Run — confirm red**

```bash
php artisan test --compact --filter='GeneralSettings is resolvable|writing to GeneralSettings'
```
Expected: FAIL — class doesn't exist.

- [ ] **Step 3: Implement the settings class**

Create `app/Settings/GeneralSettings.php`:

```php
<?php

declare(strict_types=1);

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class GeneralSettings extends Settings
{
    public string $site_name;

    public string $site_description;

    public string $contact_email;

    public string $default_seo_description;

    public ?string $default_og_image;

    public static function group(): string
    {
        return 'general';
    }
}
```

- [ ] **Step 4: Register in config**

Edit `config/settings.php` — add to the `settings` array:

```php
'settings' => [
    \App\Settings\GeneralSettings::class,
],
```

Confirm `migrations_paths` includes `database/settings`. If absent, add it:

```php
'migrations_paths' => [
    database_path('settings'),
],
```

- [ ] **Step 5: Create initial values migration**

```bash
php artisan make:settings-migration CreateGeneralSettings
```

This creates `database/settings/<ts>_create_general_settings.php`. Edit it:

```php
<?php

declare(strict_types=1);

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('general.site_name', 'ForgeCMS');
        $this->migrator->add('general.site_description', 'A Laravel + Filament + Inertia content management system.');
        $this->migrator->add('general.contact_email', 'admin@example.com');
        $this->migrator->add('general.default_seo_description', 'Built with ForgeCMS.');
        $this->migrator->add('general.default_og_image', null);
    }

    public function down(): void
    {
        $this->migrator->deleteIfExists('general.site_name');
        $this->migrator->deleteIfExists('general.site_description');
        $this->migrator->deleteIfExists('general.contact_email');
        $this->migrator->deleteIfExists('general.default_seo_description');
        $this->migrator->deleteIfExists('general.default_og_image');
    }
};
```

- [ ] **Step 6: Migrate**

```bash
php artisan migrate --no-interaction
```

- [ ] **Step 7: Run — confirm green**

```bash
php artisan test --compact --filter='GeneralSettings is resolvable|writing to GeneralSettings'
```
Expected: PASS.

#### Pint

- [ ] **Step 8: Format**

```bash
vendor/bin/pint --dirty --format agent
```

#### CR Loop

- [ ] **Step 9: Dispatch code reviewer**

Prompt:
> Review unstaged diff: GeneralSettings class, settings migration, config registration. Verify: (a) public typed properties match the spec field list (site_name, site_description, contact_email, default_seo_description, default_og_image), (b) migration `down()` is fully reversible, (c) `default_og_image` is nullable and `null` is a valid initial value, (d) no `env()` calls inside the settings class itself (env is for config files only per forge-cms-overrides.md). Reference forge-cms-overrides.md §7.

- [ ] **Step 10: Fix flagged issues** (loop with tests + pint).

- [ ] **Step 11: Re-dispatch CR until clean.**

#### Commit

- [ ] **Step 12: Commit**

```bash
git add app/Settings/GeneralSettings.php \
        database/settings/*_create_general_settings.php \
        config/settings.php \
        tests/Feature/Admin/SystemSettingsPageTest.php
git commit -m "feat(settings): GeneralSettings class with initial values migration"
```

---

### Task 4: SystemSettings Filament Page

**Files:**
- Create: `app/Filament/Pages/SystemSettings.php`
- Modify (auto if Filament discovers): or add `->pages([SystemSettings::class])` in `app/Providers/Filament/AdminPanelProvider.php`
- Modify: `tests/Feature/Admin/SystemSettingsPageTest.php`

#### TDD

- [ ] **Step 1: Write the failing tests**

Append to `tests/Feature/Admin/SystemSettingsPageTest.php`:

```php
use App\Filament\Pages\SystemSettings;
use App\Models\User;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    Role::findOrCreate('super_admin');
});

test('SystemSettings page lives under 系统 nav group', function (): void {
    expect(SystemSettings::getNavigationGroup())->toBe('系统')
        ->and(SystemSettings::getNavigationSort())->toBe(4)
        ->and(SystemSettings::getNavigationLabel())->toBe('配置');
});

test('super_admin can render the system settings page', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');

    $this->actingAs($admin)
        ->get(SystemSettings::getUrl())
        ->assertSuccessful();
});

test('guests are redirected from the system settings page to login', function (): void {
    $this->get(SystemSettings::getUrl())->assertRedirect('/admin/login');
});

test('saving the form persists GeneralSettings values', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');

    Livewire::actingAs($admin)
        ->test(SystemSettings::class)
        ->fillForm([
            'site_name' => 'Updated Brand',
            'site_description' => 'New description',
            'contact_email' => 'hello@example.com',
            'default_seo_description' => 'New SEO line',
            'default_og_image' => 'https://example.com/og.png',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    app()->forgetInstance(\App\Settings\GeneralSettings::class);
    $reloaded = app(\App\Settings\GeneralSettings::class);

    expect($reloaded->site_name)->toBe('Updated Brand')
        ->and($reloaded->default_og_image)->toBe('https://example.com/og.png');
});
```

- [ ] **Step 2: Run — confirm red**

```bash
php artisan test --compact --filter=SystemSettings
```
Expected: FAIL — page class doesn't exist.

- [ ] **Step 3: Implement the page**

Create `app/Filament/Pages/SystemSettings.php`:

```php
<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Settings\GeneralSettings;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Pages\Page;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Livewire\Attributes\Url;

class SystemSettings extends Page implements HasSchemas
{
    use InteractsWithSchemas;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static string|\UnitEnum|null $navigationGroup = '系统';

    protected static ?int $navigationSort = 4;

    protected static ?string $navigationLabel = '配置';

    protected static ?string $title = '配置';

    protected static ?string $slug = 'system-settings';

    protected string $view = 'filament.pages.system-settings';

    /** @var array<string, mixed> */
    public array $data = [];

    public function mount(): void
    {
        $this->form->fill(app(GeneralSettings::class)->toArray());
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('site_name')->required()->maxLength(128),
                Textarea::make('site_description')->required()->rows(2)->maxLength(500),
                TextInput::make('contact_email')->email()->required()->maxLength(255),
                Textarea::make('default_seo_description')->required()->rows(2)->maxLength(500),
                TextInput::make('default_og_image')
                    ->url()
                    ->maxLength(2048)
                    ->nullable()
                    ->helperText('Absolute URL to the default Open Graph image.'),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $state = $this->form->getState();

        $settings = app(GeneralSettings::class);
        foreach ($state as $key => $value) {
            $settings->{$key} = $value;
        }
        $settings->save();

        \Filament\Notifications\Notification::make()
            ->title('Settings saved')
            ->success()
            ->send();
    }

    /**
     * @return array<int, Action>
     */
    protected function getFormActions(): array
    {
        return [
            Action::make('save')->label('Save')->submit('save'),
        ];
    }

    public static function canAccess(): bool
    {
        return auth()->check() && auth()->user()->hasRole('super_admin');
    }
}
```

- [ ] **Step 4: Create the view**

Create `resources/views/filament/pages/system-settings.blade.php`:

```blade
<x-filament-panels::page>
    <form wire:submit="save">
        {{ $this->form }}

        <div class="mt-6 flex justify-end gap-3">
            @foreach ($this->getFormActions() as $action)
                {{ $action }}
            @endforeach
        </div>
    </form>
</x-filament-panels::page>
```

- [ ] **Step 5: Confirm Filament discovery picks up the page**

`AdminPanelProvider` already calls `->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')` — the new Page should be auto-registered. Verify:

```bash
php artisan route:list --path=admin | grep system-settings
```

Expected: a route like `GET admin/system-settings  ... › SystemSettings`.

- [ ] **Step 6: Run tests — confirm green**

```bash
php artisan test --compact --filter=SystemSettings
```
Expected: all PASS.

If `assertSuccessful()` fails with a Vite manifest error, follow the project pattern from `tests/Feature/Web/PostPageTest.php:21` — add `$this->withoutVite()` in the test's `beforeEach`. (Filament admin shouldn't trigger Vite for this view, so first investigate why before suppressing.)

#### Pint

- [ ] **Step 7: Format**

```bash
vendor/bin/pint --dirty --format agent
```

#### CR Loop

- [ ] **Step 8: Dispatch code reviewer**

Prompt:
> Review the new Filament Page `SystemSettings` and its blade view. Verify: (a) Page extends Filament 5 base correctly with HasSchemas + InteractsWithSchemas, (b) `mount()` hydrates from `app(GeneralSettings::class)->toArray()` — and that this method exists on Spatie's Settings class (it does, but confirm), (c) `save()` writes back property-by-property and calls `->save()` on the settings instance (NOT a static facade), (d) `canAccess()` is the authoritative gate — there is no other route binding that could leak the page, (e) the slug `'system-settings'` makes the URL `/admin/system-settings`, (f) the test for nav group asserts on the public `getNavigationGroup()` accessor (not the protected property). Reference forge-cms-overrides.md.

- [ ] **Step 9: Fix flagged issues** (loop with tests + pint).

- [ ] **Step 10: Re-dispatch CR until clean.**

#### Commit

- [ ] **Step 11: Commit**

```bash
git add app/Filament/Pages/SystemSettings.php \
        resources/views/filament/pages/system-settings.blade.php \
        tests/Feature/Admin/SystemSettingsPageTest.php
git commit -m "feat(settings): SystemSettings Filament page bound to GeneralSettings"
```

---

### Task 5: Permission gating + smoke verification

**Files:**
- Modify: `tests/Feature/Admin/SystemSettingsPageTest.php`
- Modify (optional): `app/Filament/Pages/SystemSettings.php` (switch to permission-based check if super_admin role is too coarse)

This task formalizes the access rule and writes the negative-case test.

#### TDD

- [ ] **Step 1: Write the failing test**

Append:

```php
test('a non-super_admin authenticated user cannot access system settings', function (): void {
    Role::findOrCreate('admin');
    $editor = User::factory()->create();
    $editor->assignRole('admin');

    $this->actingAs($editor)
        ->get(SystemSettings::getUrl())
        ->assertForbidden();
});

test('non-super_admin calling save() on the page is rejected', function (): void {
    Role::findOrCreate('admin');
    $editor = User::factory()->create();
    $editor->assignRole('admin');

    // Even if a non-super_admin somehow mounts the Livewire component (e.g. via
    // a crafted request), calling save() must abort. The canAccess() route gate
    // is exercised by the HTTP test above; this one exercises the component guard.
    Livewire::actingAs($editor)
        ->test(SystemSettings::class)
        ->call('save')
        ->assertForbidden();
});
```

The route-level test covers the entry point; the Livewire-level test covers the redundant `abort_unless(static::canAccess(), 403)` added in Step 3. Two assertions, two tests — no composite `or` chain (which is not valid Pest for exception classes).

- [ ] **Step 2: Run — confirm red**

```bash
php artisan test --compact --filter='non-super_admin'
```
Expected: at least one fails — current `canAccess()` may return false correctly for the GET test but the Livewire component-level check may be missing.

- [ ] **Step 3: Verify / strengthen canAccess**

Confirm `canAccess()` in `SystemSettings.php` from Task 4 returns false for non-super_admins. If the Livewire test still passes (i.e. component renders for an unauthorized user), add an explicit guard at the top of `mount()` and `save()`:

```php
public function mount(): void
{
    abort_unless(static::canAccess(), 403);

    $this->form->fill(app(GeneralSettings::class)->toArray());
}

public function save(): void
{
    abort_unless(static::canAccess(), 403);

    // ...existing body
}
```

- [ ] **Step 4: Run tests — confirm green**

```bash
php artisan test --compact --filter=SystemSettings
```
Expected: all PASS (positive + negative).

#### Pint

- [ ] **Step 5: Format**

```bash
vendor/bin/pint --dirty --format agent
```

#### CR Loop

- [ ] **Step 6: Dispatch code reviewer**

Prompt:
> Review the access-control hardening on SystemSettings. Verify: (a) `canAccess()` is the authoritative check, (b) `mount()` and `save()` redundantly call `abort_unless(static::canAccess(), 403)` — defense in depth, (c) tests cover both the route-level redirect (guest) AND the component-level deny (authenticated non-super_admin), (d) no super_admin bypass slipped in. Reference forge-cms-overrides.md §3.3.

- [ ] **Step 7: Fix flagged issues** (loop).

- [ ] **Step 8: Re-dispatch CR until clean.**

#### Commit

- [ ] **Step 9: Manual smoke check**

```bash
php artisan serve  # if not already running
```

Open `http://127.0.0.1:8000/admin/system-settings` as a `super_admin`, change `site_name` to "Smoke Test Brand", click Save, refresh — value persists. Then log in as a non-super_admin user (or remove the role) and confirm 403 / no nav-bar entry.

- [ ] **Step 10: Run full suite**

```bash
php artisan test --compact
```
Expected: all green.

- [ ] **Step 11: Commit**

```bash
git add app/Filament/Pages/SystemSettings.php tests/Feature/Admin/SystemSettingsPageTest.php
git commit -m "feat(settings): defense-in-depth super_admin gating on SystemSettings page"
```

---

## Self-Review

- ✅ Spec §5.4 acceptance criteria all map to Tasks 2–5.
- ✅ CR→FIX→TDD loop in every implementing task.
- ✅ Implementer subagents end at Pint; main session commits.
- ✅ One commit per task = four commits (Tasks 2/3/4/5).
- ✅ Fallback path documented in Task 1 if package incompatible — no "TBD".
- ✅ Negative authorization test in Task 5 prevents silent over-permissive merges.
- ✅ Settings persist + reload tested via `app()->forgetInstance()` — proves no in-memory caching deception.
