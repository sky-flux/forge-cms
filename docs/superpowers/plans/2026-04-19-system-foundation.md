# 系统 Nav Group + Roles Regrouping — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a 「系统」 navigation group to the Filament admin panel, regroup the existing Filament Shield Roles resource into it, and move the 5 existing content resources into a sibling 「内容」 group.

**Architecture:** Edit `app/Providers/Filament/AdminPanelProvider.php` to declare nav-group order and apply `FilamentShieldPlugin::make()->navigationGroup('系统')`. Add a `$navigationGroup = '内容'` property to each of the 5 existing Filament resources. New System modules (Users, Dictionary, Settings — separate plans) declare `'系统'` themselves via the same property pattern.

**Tech Stack:** Filament 4, bezhansalleh/filament-shield 4, spatie/laravel-permission 7, Pest 4.

**Spec:** `docs/superpowers/specs/2026-04-19-system-admin-modules.md`

---

## Workflow per Task (project-mandated)

Each Task below MUST follow this loop and produce **exactly one commit** at the end:

1. **TDD** — write failing test → run → confirm red → implement minimal code → run → confirm green
2. **Pint** — `vendor/bin/pint --dirty --format agent`
3. **CR** — dispatch `pr-review-toolkit:code-reviewer` subagent against the unstaged diff
4. **FIX** — address every issue the reviewer flags (re-run tests after each fix)
5. **Loop** — re-dispatch CR until it returns clean
6. **Commit** — main session (not the subagent) creates ONE commit for the whole task

If executed via `superpowers:subagent-driven-development`, the implementer subagent does steps 1–2 only; main session orchestrates steps 3–6.

---

## File Structure

| File | Responsibility | Action |
|---|---|---|
| `app/Providers/Filament/AdminPanelProvider.php` | Panel config: nav-group order + Shield plugin grouping | Modify |
| `app/Filament/Resources/Categories/CategoryResource.php` | Filament Resource | Modify (add `$navigationGroup`) |
| `app/Filament/Resources/Comments/CommentResource.php` | Filament Resource | Modify |
| `app/Filament/Resources/Pages/PageResource.php` | Filament Resource | Modify |
| `app/Filament/Resources/Posts/PostResource.php` | Filament Resource | Modify |
| `app/Filament/Resources/Tags/TagResource.php` | Filament Resource | Modify |
| `tests/Feature/Deps/FilamentPanelTest.php` | Panel-wide assertions | Modify (3 new tests) |
| `config/filament-shield.php` | (Fallback only) Shield navigation config | Conditionally publish + modify |

---

### Task 1: Research — confirm Shield v4 fluent API

**No commit. Outputs a recorded decision used in Task 3.**

- [ ] **Step 1: Search docs**

Use Boost MCP `search-docs`:
```
queries=["filament shield navigation group", "FilamentShieldPlugin navigationGroup", "shield plugin configure navigation"]
```

- [ ] **Step 2: Locate Shield's RoleResource class**

```bash
ls vendor/bezhansalleh/filament-shield/src/Resources/
php artisan tinker --execute 'echo class_exists("\\BezhanSalleh\\FilamentShield\\Resources\\Roles\\RoleResource") ? "ok" : "missing";'
```

Verified-on-disk FQCN at planning time: `\BezhanSalleh\FilamentShield\Resources\Roles\RoleResource` (note the `Roles\` subdirectory). If `tinker` prints `missing`, run the `ls` to discover the correct path and update this plan **before** Task 3.

- [ ] **Step 3: Verify whether fluent `navigationGroup()` setter exists, then pick a path**

Run:
```bash
grep -n "function navigationGroup\|function navigationSort" vendor/bezhansalleh/filament-shield/src/FilamentShieldPlugin.php
```

- **Empty result → Path B (default).** The installed Shield version has no fluent setter; use the published-config approach.
- **Match found → Path A is available**, but only switch if the search-docs step also confirms it as the recommended v4 idiom.

**Path B (default — config publish):**
```bash
php artisan vendor:publish --provider="BezhanSalleh\FilamentShield\FilamentShieldServiceProvider" --tag="filament-shield-config"
```
Then in `config/filament-shield.php` set `'navigation' => ['group' => '系统', 'sort' => 2, 'icon' => null]`. Note: the published default uses `__('filament-shield::filament-shield.nav.group')` for `group` — replace that translation call with the literal `'系统'` so the test asserts a stable value.

**Path A (only if Step 3 grep matched):** `FilamentShieldPlugin::make()->navigationGroup('系统')->navigationSort(2)` in `AdminPanelProvider`.

Append a one-line note to this plan (e.g. `> Decision: Path B (verified — no fluent setter present)`) before starting Task 2.

---

### Task 2: Add `内容`/`系统` nav-group ordering

**Files:**
- Modify: `app/Providers/Filament/AdminPanelProvider.php`
- Modify: `tests/Feature/Deps/FilamentPanelTest.php`

#### TDD

- [ ] **Step 1: Write the failing test**

Append to `tests/Feature/Deps/FilamentPanelTest.php`:

```php
test('admin panel declares 内容 and 系统 navigation groups in that order', function (): void {
    $panel = filament()->getPanel('admin');

    $groupKeys = array_values(array_map(
        fn ($g) => is_string($g) ? $g : $g->getLabel(),
        $panel->getNavigationGroups(),
    ));

    expect($groupKeys)->toContain('内容')
        ->and($groupKeys)->toContain('系统')
        ->and(array_search('内容', $groupKeys, true))
        ->toBeLessThan(array_search('系统', $groupKeys, true));
});
```

- [ ] **Step 2: Run test — confirm red**

```bash
php artisan test --compact --filter='admin panel declares'
```
Expected: FAIL — `getNavigationGroups()` returns `[]`.

- [ ] **Step 3: Implement**

Edit `app/Providers/Filament/AdminPanelProvider.php` `panel()` method — insert immediately before `->plugins([...])`:

```php
->navigationGroups([
    '内容',
    '系统',
])
```

- [ ] **Step 4: Run test + Admin regression — confirm green**

```bash
php artisan test --compact --filter='admin panel declares'
php artisan test --compact --filter='Admin'
```
Expected: PASS for both. The Admin filter catches any sibling resource that broke from the panel-config change.

#### Pint

- [ ] **Step 5: Format**

```bash
vendor/bin/pint --dirty --format agent
```

#### CR Loop

- [ ] **Step 6: Dispatch code reviewer**

Main session only — dispatch `pr-review-toolkit:code-reviewer` subagent with prompt:
> Review the unstaged diff. Focus: (a) navigation group ordering matches the project's Filament 4 conventions, (b) test asserts the right surface (label, not internal object identity, ordering not strict equality), (c) no Octane-unsafe patterns introduced. Reference `.claude/skills/laravel-best-practices/rules/forge-cms-overrides.md`.

- [ ] **Step 7: Fix every flagged issue**

For each issue: edit → re-run `php artisan test --compact --filter='admin panel declares'` → re-run pint.

- [ ] **Step 8: Re-dispatch CR until clean**

Loop Steps 6–7 until the reviewer returns no actionable issues.

#### Commit (main session only)

- [ ] **Step 9: Commit**

```bash
git add app/Providers/Filament/AdminPanelProvider.php tests/Feature/Deps/FilamentPanelTest.php
git commit -m "feat(admin): declare 内容 and 系统 navigation groups"
```

---

### Task 3: Apply Shield `navigationGroup('系统')`

**Files:**
- Modify: `app/Providers/Filament/AdminPanelProvider.php` *(Path A)* OR `config/filament-shield.php` *(Path B)*
- Modify: `tests/Feature/Deps/FilamentPanelTest.php`

Use the FQCN recorded in Task 1 Step 2. With the installed Shield version it is `\BezhanSalleh\FilamentShield\Resources\Roles\RoleResource`.

#### TDD

- [ ] **Step 1: Write the failing test**

Append to `tests/Feature/Deps/FilamentPanelTest.php`:

```php
test('roles resource appears under the 系统 navigation group', function (): void {
    $resource = \BezhanSalleh\FilamentShield\Resources\Roles\RoleResource::class;

    expect(class_exists($resource))->toBeTrue() // guards against an upstream rename
        ->and($resource::getNavigationGroup())->toBe('系统');
});
```

- [ ] **Step 2: Run test — confirm red**

```bash
php artisan test --compact --filter='roles resource appears'
```
Expected: FAIL — current value is `"Filament Shield"` (translated default) or `null`.

- [ ] **Step 3: Implement (Path B — default)**

Run the publish command if not already done in Task 1:
```bash
php artisan vendor:publish --provider="BezhanSalleh\FilamentShield\FilamentShieldServiceProvider" --tag="filament-shield-config"
```

Edit `config/filament-shield.php` — set the `navigation` key to literal values (no `__()` translation, so the test assertion is stable):

```php
'navigation' => [
    'group' => '系统',
    'sort' => 2,
    'icon' => null,
],
```

**Path A (only if Task 1 confirmed the fluent setter exists):** instead of publishing the config, edit `app/Providers/Filament/AdminPanelProvider.php`:

```php
->plugins([
    FilamentShieldPlugin::make()
        ->navigationGroup('系统')
        ->navigationSort(2),
]);
```

- [ ] **Step 4: Run test + Admin regression — confirm green**

```bash
php artisan test --compact --filter='roles resource appears'
php artisan test --compact --filter='Admin'
```
Expected: both PASS.

#### Pint

- [ ] **Step 5: Format**

```bash
vendor/bin/pint --dirty --format agent
```

#### CR Loop

- [ ] **Step 6: Dispatch code reviewer**

Prompt:
> Review unstaged diff for the Shield navigationGroup change. Confirm (a) chosen path matches Shield v4 idiomatic config, (b) no breaking change to existing Roles permissions or routes, (c) the test queries `getNavigationGroup()` against a stable public API. Reference forge-cms-overrides.md.

- [ ] **Step 7: Fix flagged issues** (loop with test + pint).

- [ ] **Step 8: Re-dispatch CR until clean.**

#### Commit (main session only)

- [ ] **Step 9: Commit**

```bash
git add tests/Feature/Deps/FilamentPanelTest.php
# Path B (default): also git add config/filament-shield.php
# Path A (only if used): also git add app/Providers/Filament/AdminPanelProvider.php
git commit -m "feat(admin): regroup filament-shield roles into 系统"
```

---

### Task 4: Move 5 existing resources into the 内容 group

**Files:**
- Modify: `app/Filament/Resources/Categories/CategoryResource.php`
- Modify: `app/Filament/Resources/Comments/CommentResource.php`
- Modify: `app/Filament/Resources/Pages/PageResource.php`
- Modify: `app/Filament/Resources/Posts/PostResource.php`
- Modify: `app/Filament/Resources/Tags/TagResource.php`
- Modify: `tests/Feature/Deps/FilamentPanelTest.php`

#### TDD

- [ ] **Step 1: Write the failing test**

Append to `tests/Feature/Deps/FilamentPanelTest.php`:

```php
test('content resources are grouped under 内容 with stable sort order', function (): void {
    expect(\App\Filament\Resources\Posts\PostResource::getNavigationGroup())->toBe('内容')
        ->and(\App\Filament\Resources\Posts\PostResource::getNavigationSort())->toBe(1)
        ->and(\App\Filament\Resources\Pages\PageResource::getNavigationGroup())->toBe('内容')
        ->and(\App\Filament\Resources\Pages\PageResource::getNavigationSort())->toBe(2)
        ->and(\App\Filament\Resources\Categories\CategoryResource::getNavigationGroup())->toBe('内容')
        ->and(\App\Filament\Resources\Categories\CategoryResource::getNavigationSort())->toBe(3)
        ->and(\App\Filament\Resources\Tags\TagResource::getNavigationGroup())->toBe('内容')
        ->and(\App\Filament\Resources\Tags\TagResource::getNavigationSort())->toBe(4)
        ->and(\App\Filament\Resources\Comments\CommentResource::getNavigationGroup())->toBe('内容')
        ->and(\App\Filament\Resources\Comments\CommentResource::getNavigationSort())->toBe(5);
});
```

- [ ] **Step 2: Run test — confirm red**

```bash
php artisan test --compact --filter='content resources are grouped'
```
Expected: FAIL — all `getNavigationGroup()` return `null`.

- [ ] **Step 3: Implement**

Add these two properties to each Resource class, immediately under `protected static ?string $model = ...;`:

```php
protected static string|\UnitEnum|null $navigationGroup = '内容';

protected static ?int $navigationSort = <N>;
```

with `<N>` per resource:
- `PostResource` → 1
- `PageResource` → 2
- `CategoryResource` → 3
- `TagResource` → 4
- `CommentResource` → 5

- [ ] **Step 4: Run all admin + panel tests — confirm green and no regression**

```bash
php artisan test --compact --filter='Admin'
php artisan test --compact --filter='FilamentPanel'
```
Expected: all pass.

#### Pint

- [ ] **Step 5: Format**

```bash
vendor/bin/pint --dirty --format agent
```

#### CR Loop

- [ ] **Step 6: Dispatch code reviewer**

Prompt:
> Review unstaged diff. Five Filament Resources got `$navigationGroup` + `$navigationSort` properties. Confirm: (a) property type signature matches Filament 4 (`string|\UnitEnum|null`), (b) sort order is sensible for editorial UX (Posts → Pages → Categories → Tags → Comments), (c) no other code paths broke (existing tests pass). Reference forge-cms-overrides.md.

- [ ] **Step 7: Fix flagged issues** (loop with tests + pint).

- [ ] **Step 8: Re-dispatch CR until clean.**

#### Commit (main session only)

- [ ] **Step 9: Manual smoke check**

```bash
# In another terminal if not already running:
php artisan serve
```

Open `http://127.0.0.1:8000/admin`, log in as a `super_admin`, confirm the sidebar shows: **内容** (Posts → Pages → Categories → Tags → Comments) then **系统** (Roles). Stop and report if anything is off — do NOT silent-patch.

- [ ] **Step 10: Run full suite**

```bash
php artisan test --compact
```
Expected: all green.

- [ ] **Step 11: Commit**

```bash
git add app/Filament/Resources/Categories/CategoryResource.php \
        app/Filament/Resources/Comments/CommentResource.php \
        app/Filament/Resources/Pages/PageResource.php \
        app/Filament/Resources/Posts/PostResource.php \
        app/Filament/Resources/Tags/TagResource.php \
        tests/Feature/Deps/FilamentPanelTest.php
git commit -m "feat(admin): regroup existing content resources under 内容"
```

---

## Self-Review

- ✅ Spec §5.1 acceptance criteria all map to Tasks 2–4.
- ✅ No "TBD" / placeholder steps; every test code block is concrete.
- ✅ CR→FIX→TDD loop explicit in every task per project memory.
- ✅ Implementer subagents end at Pint; main session commits.
- ✅ One commit per task = three commits total (Tasks 2/3/4). Task 1 is research-only.
- ✅ Path B fallback fully specified — no "figure it out".
