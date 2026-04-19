# MVP Scheduled Post Publisher — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development. Each task = 1 commit. Implementer never commits.

**Worktree:** `.worktrees/mvp-scheduled-publisher` on `feat/mvp-scheduled-publisher` (branches from main).
**Tech:** Laravel 13 (console commands + scheduler in `bootstrap/app.php`), Pest 4.
**Spec:** `docs/superpowers/specs/2026-04-19-mvp-completion-batch-1.md` § Worktree B.

## Workflow per Task

1. Write failing Pest test first → RED
2. Minimal implementation → GREEN
3. `vendor/bin/pint --dirty --format agent`
4. Controller dispatches `pr-review-toolkit:code-reviewer` → fix loop → CR clean
5. Controller commits
6. Done

## Project Conventions

- `declare(strict_types=1);` on every PHP file
- Pest: `test('...')` + `$this->artisan(...)` for command tests — NO `use function Pest\Laravel\...`
- Post model uses UUIDv7 PK via `HasUuids` — use factory methods, don't hand-craft IDs
- Run tests: `php artisan test --compact --filter=PublishScheduledPosts`

---

### Task 1 — `posts:publish-scheduled` command + schedule wiring

**Files:**
- Create: `app/Console/Commands/PublishScheduledPosts.php`
- Modify: `bootstrap/app.php` (add `->withSchedule(...)`)
- Create: `tests/Feature/Console/PublishScheduledPostsTest.php`

#### Pre-flight

```bash
cd /Users/martinadamsdev/workspace/forge-cms/.worktrees/mvp-scheduled-publisher
cat app/Enums/PostStatus.php  # confirm enum cases Draft/Scheduled/Published
grep -n 'scheduled\|published' database/factories/PostFactory.php  # confirm factory state method
```

#### TDD

**Step 1 — failing test.** Create `tests/Feature/Console/PublishScheduledPostsTest.php`:

```php
<?php

declare(strict_types=1);

use App\Enums\PostStatus;
use App\Models\Post;

test('scheduled posts with past published_at flip to Published', function (): void {
    $post = Post::factory()->create([
        'status' => PostStatus::Scheduled,
        'published_at' => now()->subMinute(),
    ]);

    $this->artisan('posts:publish-scheduled')->assertSuccessful();

    expect($post->fresh()->status)->toBe(PostStatus::Published);
});

test('scheduled posts with future published_at stay Scheduled', function (): void {
    $post = Post::factory()->create([
        'status' => PostStatus::Scheduled,
        'published_at' => now()->addHour(),
    ]);

    $this->artisan('posts:publish-scheduled')->assertSuccessful();

    expect($post->fresh()->status)->toBe(PostStatus::Scheduled);
});

test('draft posts stay Draft regardless of published_at', function (): void {
    $post = Post::factory()->create([
        'status' => PostStatus::Draft,
        'published_at' => now()->subDay(),
    ]);

    $this->artisan('posts:publish-scheduled')->assertSuccessful();

    expect($post->fresh()->status)->toBe(PostStatus::Draft);
});

test('published posts are idempotent', function (): void {
    $post = Post::factory()->create([
        'status' => PostStatus::Published,
        'published_at' => now()->subWeek(),
    ]);
    $originalUpdatedAt = $post->updated_at;

    $this->artisan('posts:publish-scheduled')->assertSuccessful();

    $fresh = $post->fresh();
    expect($fresh->status)->toBe(PostStatus::Published)
        ->and($fresh->updated_at->toIso8601String())->toBe($originalUpdatedAt->toIso8601String());
});

test('command reports how many posts were published', function (): void {
    Post::factory()->count(3)->create([
        'status' => PostStatus::Scheduled,
        'published_at' => now()->subMinute(),
    ]);

    $this->artisan('posts:publish-scheduled')
        ->expectsOutputToContain('3')
        ->assertSuccessful();
});
```

**Step 2.** Run — expect RED:
```bash
php artisan test --compact --filter=PublishScheduledPostsTest
```

**Step 3 — create command.** Run:
```bash
php artisan make:command PublishScheduledPosts --no-interaction
```

Edit `app/Console/Commands/PublishScheduledPosts.php`:

```php
<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\PostStatus;
use App\Models\Post;
use Illuminate\Console\Command;

class PublishScheduledPosts extends Command
{
    protected $signature = 'posts:publish-scheduled';

    protected $description = 'Flip Scheduled posts whose published_at has passed to Published.';

    public function handle(): int
    {
        $query = Post::query()
            ->where('status', PostStatus::Scheduled)
            ->where('published_at', '<=', now());

        $count = 0;

        $query->cursor()->each(function (Post $post) use (&$count): void {
            $post->status = PostStatus::Published;
            $post->save();
            $count++;

            \Illuminate\Support\Facades\Log::info('post.auto_published', [
                'post_id' => $post->getKey(),
                'published_at' => (string) $post->published_at,
            ]);
        });

        $this->info("Published {$count} scheduled post(s).");

        return self::SUCCESS;
    }
}
```

**Step 4 — register schedule** in `bootstrap/app.php`. After the existing `->withExceptions(...)` block and before `->create()`, insert:

```php
->withSchedule(function (\Illuminate\Console\Scheduling\Schedule $schedule): void {
    $schedule->command('posts:publish-scheduled')
        ->everyMinute()
        ->withoutOverlapping()
        ->onOneServer();
})
```

Required import at top of `bootstrap/app.php`:
```php
use Illuminate\Console\Scheduling\Schedule;
```

(Then reference `Schedule $schedule` instead of the fully-qualified name if preferred. The closure signature can use either.)

**Step 5 — run tests** — expect GREEN:
```bash
php artisan test --compact --filter=PublishScheduledPostsTest
```

**Step 6 — Pint:**
```bash
vendor/bin/pint --dirty --format agent
```

**Step 7 — Controller CR + commit.** Message:
```
feat(posts): publish scheduled posts via posts:publish-scheduled cron command
```

---

## Self-Review

- Single task = single commit on `feat/mvp-scheduled-publisher`
- Tests cover the 4 critical paths (scheduled-past flips, scheduled-future stays, draft stays, published idempotent) + output assertion
- `onOneServer()` for multi-server safety; `withoutOverlapping()` for long-running edge cases
- Uses `cursor()` to stream large result sets without memory blow-up
- Logs `post.auto_published` structured context (per forge-cms-overrides §5.3)
- `bootstrap/app.php` edit is narrow and additive
- `Schedule` import may collide with existing imports — verify before committing

## Acceptance (when merged)

- `php artisan posts:publish-scheduled` works standalone
- `php artisan schedule:list` shows `posts:publish-scheduled` running every minute
- In production, a cron `* * * * * cd /app && php artisan schedule:run >/dev/null` executes it
