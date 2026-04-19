# MVP Completion — Batch 2 Spec

**Date:** 2026-04-19
**Scope:** Media library admin UI (US-031/032) + nested comment thread rendering (US-073) + new-comment notification (US-050/072). Split across 2 parallel worktrees.

## Worktree C — `feat/mvp-media-library` (single task)

Filament resource browsing `spatie/laravel-medialibrary`'s `media` table. Purpose:
- List all uploaded media with preview + metadata (name, size, mime, collection, attached model).
- Filter by mime-type prefix (image/* vs other).
- Delete a media row (triggers Spatie cleanup).
- Report orphans (media with no owner): button/filter to find media rows whose `model_id` no longer exists.

**Files:**
- Create: `app/Filament/Resources/Media/MediaResource.php` + Pages/ + Schemas/ + Tables/ (split-file)
- Create: `tests/Feature/Admin/MediaResourceTest.php`
- Register under `内容` nav group with sort 6.
- Policy: Shield-generated + custom rules (only super_admin can force delete orphans).

## Worktree D — `feat/mvp-comment-polish` (2 tasks, sequential)

### Task 1 — Nested comment thread rendering

The `Comment` DB already has `parent_id` and enforces 3-level depth. Frontend currently shows a flat list.

**Files:**
- Modify: `app/Http/Resources/CommentResource.php` — include `replies` relation (eager-loaded) recursively (or flatten to 3 levels).
- Modify: `app/Http/Controllers/Web/PostController@show` + `PageController@show` — eager-load nested comments tree: `with('comments.replies.replies')`.
- Modify: `resources/js/components/CommentThread.tsx` — render reply children indented; recursive component up to depth 3.
- Modify: `resources/js/components/CommentForm.tsx` — accept `parentId` prop; "Reply" button on each comment opens inline form.
- Create: `tests/Feature/Web/NestedCommentsTest.php` — asserts (a) replies appear under parent in response, (b) depth-3 reply is returned, (c) comment form submits with `parent_id`.

### Task 2 — New comment notification

When a guest or user submits a comment that's pending moderation, notify admins.

**Files:**
- Create: `app/Notifications/NewCommentPendingNotification.php` — Notification class with `via(['mail', 'database'])`, renders Markdown with comment body + approve/spam action URLs.
- Modify: `app/Models/Comment.php::booted()` or `CommentObserver` — on created (guest/pending), dispatch notification to all super_admin users.
- Create: `tests/Feature/Notifications/NewCommentPendingTest.php` — asserts notification dispatched, respects `ShouldQueue`, includes correct recipient (super_admin).

## Workflow (per task)

TDD → Pint → combined CR → fix loop → controller commits. 1 task = 1 commit.

## Acceptance (merged to main)

- `/console/media` admin resource lists media; super_admin can delete
- Comment thread on `/posts/{uuid}` shows nested replies up to 3 levels with inline reply UI
- Submitting a new comment triggers notification to super_admin(s)
- All tests green
