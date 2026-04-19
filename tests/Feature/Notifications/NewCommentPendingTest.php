<?php

declare(strict_types=1);

use App\Enums\CommentStatus;
use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use App\Notifications\NewCommentPendingNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
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
        ->implementsInterface(ShouldQueue::class))->toBeTrue();
});
