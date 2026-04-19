# MVP Comment Polish — Implementation Plan

> REQUIRED SUB-SKILL: superpowers:subagent-driven-development. 1 task = 1 commit. Implementer never commits.

**Worktree:** `.worktrees/mvp-comment-polish` on `feat/mvp-comment-polish` from main.
**Stack:** Laravel 13, Inertia 3 (React), Pest 4, Laravel Notifications.
**Spec:** `docs/superpowers/specs/2026-04-19-mvp-completion-batch-2.md` § Worktree D.

## Workflow per Task

TDD → Pint → combined CR → fix loop → controller commits. 1 task = 1 commit.

## Conventions (strict)

- `declare(strict_types=1);`
- Pest: `test('...')` + `$this->get(...)` + `->assertInertia(...)` + `Notification::fake()` + `->assertSent(...)` — NO `Pest\Laravel\...` imports
- Eager-load relations — preventLazyLoading is on
- Notifications queue via `implements ShouldQueue`
- `withoutVite()` in test `beforeEach` for Inertia routes

## Pre-flight

```bash
cd /Users/martinadamsdev/workspace/forge-cms/.worktrees/mvp-comment-polish

# Inspect Comment model + observer
cat app/Models/Comment.php
ls app/Observers/
grep -n 'body_html\|CommentObserver\|sanitize' app/ -R | head

# CommentResource shape
cat app/Http/Resources/CommentResource.php

# PostController@show current eager-load
cat app/Http/Controllers/Web/PostController.php

# Existing CommentThread/CommentForm components
cat resources/js/components/CommentThread.tsx
cat resources/js/components/CommentForm.tsx

# super_admin detection + notifications table
grep -n 'super_admin\|role(' app/Models/User.php app/Providers/AppServiceProvider.php
php artisan migrate:status 2>&1 | grep -i notification
```

**Important:** the project already sanitizes `body_html` on save via CommentObserver (see commit `3ce3d12 fix(comments): render body_html via CommentObserver on every save`). The frontend can render the stored HTML trusting the backend sanitization; rendering choice is up to the implementer — any mechanism that renders stored HTML (an existing sanitized-HTML wrapper, a markdown-rendered component, etc.) is acceptable, as long as the HTML is not re-escaped (that would show raw tags to the reader).

---

### Task 1 — Nested comment thread rendering

**Files:**
- Modify: `app/Http/Resources/CommentResource.php` (include `replies` recursively)
- Modify: `app/Models/Comment.php` (add/verify `replies()` relation constrained to approved)
- Modify: `app/Http/Controllers/Web/PostController.php::show` + `PageController.php::show` (eager-load `comments.replies.replies` + `user`)
- Modify: `app/Http/Controllers/Web/CommentController.php` (accept `parent_id`, enforce max depth 3)
- Modify: `resources/js/components/CommentThread.tsx` (render recursively, 3-level depth, reply toggle)
- Modify: `resources/js/components/CommentForm.tsx` (accept `parentId` prop; include in submission)
- Create: `tests/Feature/Web/NestedCommentsTest.php`

#### TDD

**Step 1 — failing tests.** Create `tests/Feature/Web/NestedCommentsTest.php`:

```php
<?php

declare(strict_types=1);

use App\Enums\CommentStatus;
use App\Models\Comment;
use App\Models\Post;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->withoutVite();
});

test('post show returns nested comment tree up to depth 3', function (): void {
    $post = Post::factory()->published()->create();

    $parent = Comment::factory()->for($post, 'commentable')->create([
        'status' => CommentStatus::Approved,
        'parent_id' => null,
    ]);
    $child = Comment::factory()->for($post, 'commentable')->create([
        'status' => CommentStatus::Approved,
        'parent_id' => $parent->id,
    ]);
    $grandchild = Comment::factory()->for($post, 'commentable')->create([
        'status' => CommentStatus::Approved,
        'parent_id' => $child->id,
    ]);

    $this->get(route('posts.show', ['post' => $post]))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Posts/Show')
            ->has('comments', 1)
            ->where('comments.0.id', $parent->id)
            ->has('comments.0.replies', 1)
            ->where('comments.0.replies.0.id', $child->id)
            ->has('comments.0.replies.0.replies', 1)
            ->where('comments.0.replies.0.replies.0.id', $grandchild->id)
        );
});

test('comment submission accepts parent_id for replies', function (): void {
    $post = Post::factory()->published()->create();
    $parent = Comment::factory()->for($post, 'commentable')->create([
        'status' => CommentStatus::Approved,
        'parent_id' => null,
    ]);

    $this->post(route('posts.comments.store', ['post' => $post]), [
        'author_name' => 'Jane',
        'author_email' => 'jane@example.com',
        'body' => 'Reply to parent.',
        'parent_id' => $parent->id,
    ])->assertRedirect();

    expect(Comment::where('parent_id', $parent->id)->exists())->toBeTrue();
});

test('comment submission rejects depth-4 replies', function (): void {
    $post = Post::factory()->published()->create();
    $l1 = Comment::factory()->for($post, 'commentable')->create(['parent_id' => null]);
    $l2 = Comment::factory()->for($post, 'commentable')->create(['parent_id' => $l1->id]);
    $l3 = Comment::factory()->for($post, 'commentable')->create(['parent_id' => $l2->id]);

    $response = $this->post(route('posts.comments.store', ['post' => $post]), [
        'author_name' => 'Jane',
        'author_email' => 'jane@example.com',
        'body' => 'Too deep.',
        'parent_id' => $l3->id,
    ]);

    $response->assertSessionHasErrors('parent_id');
});
```

**Step 2.** RED.

**Step 3 — Comment model replies() relation.** In `app/Models/Comment.php`:

```php
/**
 * @return \Illuminate\Database\Eloquent\Relations\HasMany<Comment, $this>
 */
public function replies(): \Illuminate\Database\Eloquent\Relations\HasMany
{
    return $this->hasMany(Comment::class, 'parent_id')
        ->where('status', CommentStatus::Approved)
        ->orderBy('created_at');
}
```

(If already present, confirm it filters on approved status and ordering. Adapt if scope differs.)

**Step 4 — CommentResource.** Include replies recursively:

```php
public function toArray(Request $request): array
{
    return [
        'id' => $this->id,
        'body_html' => (string) $this->body_html,
        'author_name' => (string) $this->author_name,
        'parent_id' => $this->parent_id,
        'created_at' => optional($this->created_at)->toIso8601String(),
        'replies' => CommentResource::collection($this->whenLoaded('replies')),
    ];
}
```

**Step 5 — Eager-load in PostController::show.** Replace the comments load:

```php
$post->load([
    'user',
    'categories',
    'tags',
]);

$comments = $post->comments()
    ->whereNull('parent_id')
    ->where('status', \App\Enums\CommentStatus::Approved)
    ->with(['replies.replies'])
    ->orderBy('created_at')
    ->get();

return Inertia::render('Posts/Show', [
    // ...existing props...
    'comments' => CommentResource::collection($comments),
    // ...existing SEO props from Task 4 (canonical, ogImage) remain untouched...
]);
```

Same shape for `PageController::show`.

**Step 6 — CommentController depth guard.** In whatever FormRequest or controller validates comment submission:

```php
'parent_id' => [
    'nullable',
    'integer',
    \Illuminate\Validation\Rule::exists('comments', 'id')
        ->where(fn ($q) => $q->where('status', \App\Enums\CommentStatus::Approved)),
],
```

After validation, before `Comment::create()`, compute depth by walking up parents and reject if it would become the 4th level:

```php
if ($validated['parent_id'] ?? null) {
    $parent = Comment::findOrFail($validated['parent_id']);
    $depth = 1;
    $cursor = $parent;
    while ($cursor->parent_id !== null && $depth < 5) {
        $cursor = Comment::find($cursor->parent_id);
        if ($cursor === null) { break; }
        $depth++;
    }
    if ($depth >= 3) {
        return back()->withErrors(['parent_id' => 'Replies beyond depth 3 are not allowed.'])->withInput();
    }
}
```

(If CommentController uses a dedicated FormRequest, put the depth check in an `after` validator closure instead.)

**Step 7 — CommentThread.tsx (recursive, indented).**

```tsx
import CommentForm from '@/components/CommentForm';
import { useState } from 'react';

type Comment = {
  id: number;
  body_html: string;
  author_name: string;
  parent_id: number | null;
  created_at: string;
  replies?: Comment[];
};

type Props = {
  comments: Comment[];
  commentableType: 'post' | 'page';
  commentableId: string;
  depth?: number;
};

function ReplyToggle({ parentId, commentableType, commentableId }: { parentId: number; commentableType: 'post' | 'page'; commentableId: string }) {
  const [open, setOpen] = useState(false);
  return (
    <div className="mt-2">
      <button type="button" onClick={() => setOpen(!open)} className="text-sm text-blue-600 underline">
        {open ? 'Cancel' : 'Reply'}
      </button>
      {open && (
        <div className="mt-2">
          <CommentForm
            parentId={parentId}
            commentableType={commentableType}
            commentableId={commentableId}
            onSubmitted={() => setOpen(false)}
          />
        </div>
      )}
    </div>
  );
}

export default function CommentThread({ comments, commentableType, commentableId, depth = 0 }: Props) {
  return (
    <ul className={depth > 0 ? 'ml-6 border-l pl-4' : ''}>
      {comments.map((c) => (
        <li key={c.id} className="py-3">
          <div className="text-sm text-gray-600">
            {c.author_name} · {new Date(c.created_at).toLocaleString()}
          </div>
          <div className="prose">
            {/* body_html is server-sanitized by CommentObserver on save.
                Use whatever safe-HTML-rendering convention the project already uses elsewhere
                for Post/Page body_html — match sibling usage rather than introducing a new pattern. */}
            <SafeHtml html={c.body_html} />
          </div>
          {depth < 2 && (
            <ReplyToggle parentId={c.id} commentableType={commentableType} commentableId={commentableId} />
          )}
          {c.replies && c.replies.length > 0 && (
            <CommentThread
              comments={c.replies}
              commentableType={commentableType}
              commentableId={commentableId}
              depth={depth + 1}
            />
          )}
        </li>
      ))}
    </ul>
  );
}
```

**Note on `<SafeHtml>`:** The project already renders Post/Page `body_html` in `Posts/Show.tsx` and `Pages/Show.tsx` — use whatever idiom those pages use (likely a dangerouslySetInnerHTML wrapper or a dedicated `SafeHtml` / `Prose` component). **Read sibling show pages first** and match the pattern. Do not introduce a new sanitizer if one already exists.

**Step 8 — CommentForm.tsx** — extend to accept `parentId` + include hidden field:

```tsx
type Props = {
  commentableType: 'post' | 'page';
  commentableId: string;
  parentId?: number | null;
  onSubmitted?: () => void;
};

// ...existing useForm...
const form = useForm({
  author_name: '',
  author_email: '',
  body: '',
  parent_id: parentId ?? null,
});

// Same submission URL; Laravel will accept parent_id from payload.
```

**Step 9.** GREEN.

**Step 10.** Pint + commit.

Message:
```
feat(web): nested comment threads with inline reply UI up to 3 levels
```

---

### Task 2 — New comment notification

**Files:**
- Create: `app/Notifications/NewCommentPendingNotification.php`
- Modify: `app/Models/Comment.php::booted()` — dispatch on create for Pending status
- Create: `tests/Feature/Notifications/NewCommentPendingTest.php`

#### TDD

**Step 1 — failing tests.** `tests/Feature/Notifications/NewCommentPendingTest.php`:

```php
<?php

declare(strict_types=1);

use App\Enums\CommentStatus;
use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use App\Notifications\NewCommentPendingNotification;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    Role::findOrCreate('super_admin');
});

test('pending comment notifies super_admins', function (): void {
    Notification::fake();

    $admin = User::factory()->create();
    $admin->assignRole('super_admin');

    $notAdmin = User::factory()->create();

    $post = Post::factory()->published()->create();

    Comment::factory()->for($post, 'commentable')->create([
        'status' => CommentStatus::Pending,
    ]);

    Notification::assertSentTo($admin, NewCommentPendingNotification::class);
    Notification::assertNotSentTo($notAdmin, NewCommentPendingNotification::class);
});

test('approved comment does NOT notify', function (): void {
    Notification::fake();

    $admin = User::factory()->create();
    $admin->assignRole('super_admin');

    $post = Post::factory()->published()->create();

    Comment::factory()->for($post, 'commentable')->create([
        'status' => CommentStatus::Approved,
    ]);

    Notification::assertNothingSent();
});

test('notification implements ShouldQueue', function (): void {
    expect((new ReflectionClass(NewCommentPendingNotification::class))
        ->implementsInterface(\Illuminate\Contracts\Queue\ShouldQueue::class))->toBeTrue();
});
```

**Step 2.** RED.

**Step 3 — Notification class** `app/Notifications/NewCommentPendingNotification.php`:

```php
<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Comment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewCommentPendingNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Comment $comment) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('New pending comment')
            ->greeting('Hi '.$notifiable->name.',')
            ->line('A new comment is awaiting moderation.')
            ->line('Author: '.$this->comment->author_name)
            ->line('Body: '.strip_tags((string) $this->comment->body_html))
            ->action('Review comments', url('/console/comments'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'comment_id' => $this->comment->id,
            'author_name' => $this->comment->author_name,
            'commentable_type' => $this->comment->commentable_type,
            'commentable_id' => $this->comment->commentable_id,
        ];
    }
}
```

**Step 4 — Dispatch on create.** In `Comment::booted()` (or CommentObserver if the project prefers observers):

```php
static::created(function (Comment $comment): void {
    if ($comment->status !== \App\Enums\CommentStatus::Pending) {
        return;
    }

    \App\Models\User::query()
        ->role('super_admin')
        ->get()
        ->each(fn (\App\Models\User $admin) => $admin->notify(
            new \App\Notifications\NewCommentPendingNotification($comment),
        ));
});
```

(Uses Spatie/Permission's `role(...)` query scope.)

If the existing `CommentObserver` (per commit `3ce3d12`) is the canonical place for create-side hooks, add the notify logic there instead of `booted()`. Check which pattern the project uses first.

**Step 5.** GREEN.

**Step 6.** Pint + commit.

Message:
```
feat(comments): notify super_admins on pending comment submission
```

---

## Self-Review

- 2 tasks, 2 commits on `feat/mvp-comment-polish`
- Task 1: nested tree rendering backend+frontend + depth-3 guard
- Task 2: closes admin moderation loop with email + DB notification
- `Notification::fake()` avoids smtp in tests
- `notifications` table verified via `migrate:status` in pre-flight
- body_html rendering uses whichever safe-HTML idiom the project already uses elsewhere — no new sanitizer introduced

## Acceptance

- `/posts/{uuid}` renders replies nested up to depth 3
- Depth-4 submission rejected with validation error
- Submitting a pending comment dispatches mail + database notification to all super_admins
- Approved/spam comments do not trigger the notification
