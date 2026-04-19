# 系统 (System) Admin Modules — Spec

**Date:** 2026-04-19
**Status:** Approved (decisions confirmed by project owner)
**Plans derived from this spec:**
- `docs/superpowers/plans/2026-04-19-system-foundation.md`
- `docs/superpowers/plans/2026-04-19-system-users.md`
- `docs/superpowers/plans/2026-04-19-system-dictionary.md`
- `docs/superpowers/plans/2026-04-19-system-settings.md`

---

## 1. Goal

Add a new **「系统」** (System) navigation group to the Filament admin panel containing four management modules — in this priority order:

1. **用户** (Users) — manage user accounts and role assignments
2. **角色** (Roles) — existing Filament Shield resource, regrouped under 系统
3. **字典** (Dictionary) — editable key/value lookup tables for runtime-configurable enums
4. **配置** (Settings) — site-wide configuration page (single record, multi-tab)

Existing content resources (Categories, Comments, Pages, Posts, Tags) move to a sibling **「内容」** (Content) group so the panel reads as a clean two-group layout.

## 2. Non-Goals

- Replacing the existing PHP enums (`PostStatus`, `CommentStatus`) with dictionary-backed values. Dictionary is additive — only new soft-controlled lists go into it.
- Multi-tenancy or per-user settings. `配置` is global, single-record.
- A frontend (Inertia) UI for any of these modules. All four are admin-only.
- Building a `UserResource` for self-service profile editing — that already exists at `/settings/profile` via Inertia (`app/Http/Controllers/Settings/`). The new admin `UserResource` is for super_admin-only user management.
- Migrating the existing `super_admin` / `admin` / `editor` / `author` roles or their permissions — Shield's data stays as-is.

## 3. User Stories

| As a... | I want... | So that... |
|---|---|---|
| super_admin | a System group in the admin sidebar with Users/Roles/Dictionary/Settings | I have one obvious place to manage non-content concerns |
| super_admin | to create / edit / soft-delete users and assign roles | I can onboard or offboard editors without `tinker` |
| super_admin | to add a dictionary type (e.g. `external_link_channel`) and items under it | content editors can pick from a controlled list without a code change |
| editor (read-only on these) | to be denied access to all four 系统 resources | the panel doesn't expose config to non-admins |
| super_admin | to set site-wide values (site name, default SEO description, contact email) and have them survive deploys | branding/SEO updates don't require a `.env` change |

## 4. Technical Decisions (locked)

| Concern | Decision | Rationale |
|---|---|---|
| Nav group setup | `->navigationGroups(['内容', '系统'])` in `AdminPanelProvider::panel()` | Filament 4 supports declarative ordering; explicit list controls UI order. |
| Roles regrouping | **Default: publish `config/filament-shield.php` and set `navigation.group = '系统'`** (Path B). The fluent `FilamentShieldPlugin::make()->navigationGroup('系统')` (Path A) is only available in Shield versions that expose the setter — verified absent in the installed version during planning, so Path B is the default. | Path B is guaranteed to work with the installed version; Path A remains a lighter-touch option if a future Shield upgrade exposes the setter. |
| Existing resources regrouping | Add `protected static ?string $navigationGroup = '内容';` to each of the 5 existing Resources | Smallest possible change; matches the Filament-recommended class-property approach. |
| User → Role assignment | `Select::make('roles')->relationship('roles', 'name')->multiple()->preload()` | Spatie/Permission already exposes `roles()` BelongsToMany on User. |
| User authorization | `php artisan shield:generate --resource=UserResource` then a custom `UserPolicy::update` rule that forbids non-super_admin from touching another user with super_admin role | Defense-in-depth against privilege escalation. |
| Dictionary table design | Two tables: `dictionary_types(code,name,remark)` + `dictionary_items(type_id,label,value,sort,is_default,status)` | Mirrors RuoYi convention; supports per-type item management via Filament RelationManager. |
| Dictionary lookup API | `Dictionary::items(string $code): Collection` static helper, cached with `Cache::rememberForever('dict.'.$code, ...)`, busted on item save/delete via model events | O(1) hot path. Octane-safe (closure-based, no static-state mutation). |
| Settings package | `spatie/laravel-settings` + the Filament plugin `outerweb/filament-settings` (or hand-rolled Page) | Strong-typed setting classes, IDE-friendly, Filament has community plugin support. Final plugin choice verified by `search-docs` at execution time. |
| Settings shape | One `App\Settings\GeneralSettings` for v1 (site_name, site_description, contact_email, default_seo_description, default_og_image). More groups (Mail, Comments) added in follow-up PRs only when needed. | YAGNI — ship the minimum. |
| Settings UI | A single Filament **Page** (`SystemSettings`) with a Form schema bound to GeneralSettings, no tabs in v1 | One settings group → no tabs. Add Tabs schema only when a second group exists. |

## 5. Acceptance Criteria

### 5.1 Foundation
- [ ] `/admin` sidebar shows two groups: **内容** (with Categories, Comments, Pages, Posts, Tags) and **系统** (with Roles, plus future Users/Dictionary/Settings).
- [ ] Pest test asserts `FilamentShieldPlugin` is registered with `navigationGroup('系统')`.
- [ ] All 5 existing resource tests still pass (`php artisan test --compact --filter=Admin`).

### 5.2 Users
- [ ] super_admin can list users at `/admin/users`, sees columns: name, email, roles, email_verified_at, created_at.
- [ ] super_admin can create a user (name, email, password, roles).
- [ ] super_admin can edit a user including reassigning roles.
- [ ] A non-super_admin who somehow reaches `/admin/users/<id>/edit` cannot demote a super_admin (UserPolicy::update returns false).
- [ ] Soft delete works (User uses SoftDeletes — verify; if not, add `forceDelete` action and skip soft delete).
- [ ] Pest tests cover: index renders for super_admin, guests redirected, create form renders, role assignment persists, super_admin protection rule.

### 5.3 Dictionary
- [ ] super_admin can create a dictionary type (`code`, `name`, `remark`).
- [ ] super_admin can manage items under each type via a RelationManager: label, value, sort, is_default, status (enabled/disabled).
- [ ] `Dictionary::items('post_visibility')` returns a cached `Collection<DictionaryItem>` ordered by `sort`.
- [ ] Saving or deleting an item busts the cache key for its type.
- [ ] Tests cover: type CRUD, item CRUD via RelationManager, helper returns expected items, cache invalidation on save.

### 5.4 Settings
- [ ] super_admin can edit GeneralSettings at `/admin/system-settings`.
- [ ] Saved values persist across requests (verified by re-rendering form).
- [ ] Default values come from a migration that seeds initial settings.
- [ ] `app(GeneralSettings::class)->site_name` resolves anywhere via DI.
- [ ] Tests cover: page renders for super_admin, guest redirect, form submission persists, default values load on first install.

## 6. Constraints (from `rules/forge-cms-overrides.md`)

- **Octane-safe:** no static caches with request data, no runtime `config(['k' => $v])`. Dictionary cache uses `Cache::rememberForever` (Redis-backed, request-safe).
- **Casts via `casts()` method** in every new model (Dictionary*, settings models if any).
- **`$fillable` property form** for new domain models (Dictionary*); User keeps its `#[Fillable]` attribute as-is.
- **No `HasUuids`** on Dictionary models — they're not URL-routable on the public frontend, only by admin via Filament (which uses `id` by default). Sibling pattern: ContentTable models use UUIDs only because public routes bind on them.
- **Pest tests** under `tests/Feature/Admin/` matching sibling style: `test()` + `$this->actingAs()->get(...)` + `Livewire::test(...)` for table interactions. No `use function Pest\Laravel\...` imports.
- **Authorization** via Shield-generated policies + custom rules in policies; no inline `if ($user->isAdmin())`.
- **Pint:** `vendor/bin/pint --dirty --format agent` after each task.
- **Test command:** `php artisan test --compact` (filter where helpful).

## 7. Dependencies

- Foundation must complete first (touches `AdminPanelProvider.php` — central file).
- Users, Dictionary, Settings can run in parallel after Foundation.
- Settings is the only one introducing a new Composer dependency (`spatie/laravel-settings`).

## 8. Risks & Mitigations

| Risk | Mitigation |
|---|---|
| `FilamentShieldPlugin::make()->navigationGroup(...)` API may differ in v4 from what I recall | Plan 1 includes a `search-docs` step before the AdminPanelProvider edit. If the fluent API doesn't exist, fall back to publishing `config/filament-shield.php` and editing `navigation.group`. Either path satisfies the acceptance criterion. |
| `outerweb/filament-settings` may be Filament v3-only | Plan 4 includes a `search-docs` step + composer compatibility check. Fallback: hand-roll a Filament Page that hydrates/persists GeneralSettings via `mount()` and `save()`. |
| User soft-delete may not be enabled on the existing model | Plan 2 inspects the User model first; if `SoftDeletes` is absent, the plan uses hard delete only and notes a follow-up task. |
| Two AdminPanelProvider edits (Plan 1 nav groups + Plan 1 Shield navigationGroup + Plan 2 doesn't touch it) — conflict risk if Plans 2/3/4 also need provider edits | Plans 2/3/4 do NOT modify AdminPanelProvider; nav group is set per-Resource via `$navigationGroup` property. |
| Existing 5 Filament resources don't currently declare a navigation group | Plan 1 adds `protected static ?string $navigationGroup = '内容';` to each of the 5 resources in the same commit. |

## 9. Out of Scope (logged for later)

- Internationalization of the dictionary labels (`zh_CN` only for v1)
- A REST/JSON API for any of these modules
- Activity logging for Settings changes (would integrate with existing `spatie/laravel-activitylog`)
- Bulk import/export of dictionary items
- A 2FA-required gate on the Settings page

---

**Self-Review:**
- Every user story maps to acceptance criteria in §5. ✓
- Every technical decision in §4 is referenced in at least one plan. ✓
- Constraints from `forge-cms-overrides.md` enumerated explicitly. ✓
- Risks have concrete mitigations, not "TBD." ✓
