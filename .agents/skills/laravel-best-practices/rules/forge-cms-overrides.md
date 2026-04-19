# ForgeCMS-Specific Overrides

Project-specific Laravel conventions that **override or narrow** the generic rules in this skill. Everything here has been verified against the codebase. The full rationale lives in `docs/laravel.md` — this file is a short-form enforceable cheatsheet for AI edits.

**Source of truth precedence:**
1. This file (verified, enforceable)
2. `docs/laravel.md` (project style guide — detailed examples & rationale)
3. Generic `rules/*.md` in this skill
4. Upstream Laravel defaults

---

## 1. Runtime: Octane + FrankenPHP

This app runs on Octane with FrankenPHP workers in production. Every PHP edit must be **Octane-safe**.

The project-level CLAUDE.md already embeds the official Octane rules (`=== octane/core rules ===`). Before introducing any of the following, re-read that section AND `docs/laravel.md` §1:

- Static properties / class-level caches that include request or user data
- `singleton()` bindings that close over request data (use `scoped()` instead)
- `config(['key' => $value])` at runtime (persists across requests)

## 2. Eloquent conventions

### 2.1 Casts use the `casts()` method, never the `$casts` property

Verified in every model that has casts:
- `app/Models/User.php:34`
- `app/Models/Post.php:33`
- `app/Models/Page.php:31`
- `app/Models/Category.php:25`

Do not mix in `protected $casts = [...]`. The generic `rules/eloquent.md` allows either "following project convention" — this project's convention is the method form.

### 2.2 `$fillable` uses the property form — except `User`

Sibling pattern to follow:
- Domain models (Post, Page, Tag, Category) — `protected $fillable = [...]`
- `User` (Fortify auth model) — PHP 8 attribute `#[Fillable(['name', 'email', 'password'])]` (see `app/Models/User.php:22`)

When editing a model, match its sibling style. Do not migrate existing models from one form to the other without project owner approval.

### 2.3 Content models use `HasUuids` + custom route key

The four content models — `Post`, `Page`, `Tag`, `Category` — use `HasUuids` and override `getRouteKeyName(): 'uuid'`. URLs bind on UUID, not auto-increment `id`. When adding a new content model, follow the same pattern. `User` is exempt.

### 2.4 `preventLazyLoading` is on outside production

`Model::preventLazyLoading(! $this->app->isProduction())` is wired in `app/Providers/AppServiceProvider.php:31`. Any controller/Inertia page that iterates a relation MUST eager-load it with `with(...)` or the test suite throws `LazyLoadingViolationException`.

## 3. HTTP layer

### 3.1 Controller directory structure (actual, not aspirational)

Only these subdirs exist under `app/Http/Controllers/`:

- `Web/` — Inertia-rendered public frontend (Post, Page, Tag, Category shows/archives)
- `Settings/` — authenticated user self-serve (Profile, Security)
- `Fortify/` does NOT live here — Fortify's actions live in `app/Actions/Fortify/`

Do not create `Api/`, `Admin/`, or any top-level namespace without project owner approval. Admin lives entirely inside `app/Filament/`, not Controllers.

### 3.2 Web controllers return `Inertia::render(...)`, never Blade

Verified across every Web controller (PageController, PostController, CategoryController, TagController, Settings/*). Never introduce `return view('...')` in a Web controller.

### 3.3 Authorization: explicit `AuthorizesRequests` trait + policy call

The base `app/Http/Controllers/Controller.php` is the Laravel 11+ slim stub — **no** `AuthorizesRequests` by default. Child controllers must opt in:

```php
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class PostController extends Controller
{
    use AuthorizesRequests;

    public function show(Post $post): Response
    {
        $this->authorize('view', $post); // Delegates to PostPolicy::view
        // ...
    }
}
```

Verified in `PostController.php:16,34` and `PageController.php:20`. Do not inline `if ($user->isAdmin())` — every authorization check goes through a Policy.

### 3.4 Always wrap Inertia props in a Resource

Never pass raw Eloquent models into `Inertia::render`. Use `PostResource::collection($posts)` or `new PostResource($post)`. Reason: SSR serialization safety + field filtering (passwords/tokens must never leak).

## 4. Testing

### 4.1 Pest style: project uses `test()` + `$this->get(...)`, NOT `use function Pest\Laravel\...`

The functional import style from `docs/laravel.md` §8.2 is **not** used in this repo — every existing test uses `$this->get(...)`, `$this->actingAs(...)` via Pest's auto-bound `TestCase`. Match the sibling tests.

### 4.2 Inertia tests use `assertInertia(fn ($page) => $page->component(...)->where(...))`

18 callsites across 7 test files (PostPageTest, PageShowTest, CategoryArchiveTest, TagArchiveTest, SecurityTest, PasswordConfirmationTest, TwoFactorChallengeTest). When testing an Inertia page response, that is the pattern — do not invent alternative assertions.

### 4.3 `$this->withoutVite()` when touching Inertia pages pre-Vite-build

Several Web tests add `$this->withoutVite()` in `beforeEach` (see `tests/Feature/Web/PostPageTest.php:21`) because the Vite manifest may not reference the target page yet. If you add a new Inertia test and hit "Vite manifest" errors, this is the workaround — not a test bug.

## 5. Tooling

### 5.1 Pint command

```bash
vendor/bin/pint --dirty --format agent
```

Never use `--test`. Reinforced by user-memory rule `TDD cycle — every commit full green`.

### 5.2 Running tests

`php artisan test --compact` (optionally with `--filter=Name` or a file path). Do not use `./vendor/bin/pest` directly — it bypasses the project's compact output.

## 6. Intentional gaps — what `docs/laravel.md` prescribes that is NOT yet live

These are **direction-of-travel**, not enforced state. Do not cite them as "project convention" when reviewing someone else's work; do implement them the first time a real need arises and then move the item up into the sections above.

| Prescribed in `docs/laravel.md` | Current state | When introducing, update which section |
|---|---|---|
| `Inertia::lazy()` / `Inertia::optional()` for paginated props (§6.3) | zero callsites | §3 above |
| Business-domain `Action` classes (§3.2) | `app/Actions/Fortify/` only | §3.1 above |
| `Api/` REST controllers + `App\Http\Resources` API versioning (§2.2) | not present | §3.1 above |
| Queue `Job`s with tries/backoff (§7.1) | no `app/Jobs/` | New §7 |
| `JsonFormatter` on stderr log channel (§9.1) | `LOG_CHANNEL=stderr` is active in `.env`; `LOG_STDERR_FORMATTER` is unset so Monolog's default LineFormatter is used, not JSON. | New §8 |
| Rector / Larastan in CI (§12) | packages installed, CI integration TBD | §5 above |

When you make any of these live, move the row out of this table and add an "enforced" rule above with file:line citations.