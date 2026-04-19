<?php

declare(strict_types=1);

use App\Models\Page;
use App\Models\Post;
use App\Models\User;
use Spatie\Activitylog\Models\Activity;

test('records activity when a Post is created / updated / deleted', function (): void {
    $post = Post::factory()->create(['title' => 'Original Title']);

    $createdLog = Activity::query()
        ->where('subject_type', Post::class)
        ->where('event', 'created')
        ->latest('id')
        ->first();
    expect($createdLog?->description)->toBe('post.created');
    expect($createdLog?->event)->toBe('created');
    expect($createdLog?->subject_type)->toBe(Post::class);
    expect((string) $createdLog?->subject_id)->toBe((string) $post->getKey());

    $createdAttributes = $createdLog?->attribute_changes->get('attributes') ?? [];
    expect($createdAttributes['title'] ?? null)->toBe('Original Title');
    // Non-whitelisted attributes must not leak into the payload.
    expect($createdAttributes)
        ->not->toHaveKey('body_html')
        ->and($createdAttributes)->not->toHaveKey('excerpt')
        ->and($createdAttributes)->not->toHaveKey('seo_title');

    $post->update(['title' => 'Updated Title']);

    $updatedLog = Activity::query()
        ->where('subject_type', Post::class)
        ->where('event', 'updated')
        ->latest('id')
        ->first();
    expect($updatedLog?->description)->toBe('post.updated');
    expect($updatedLog?->event)->toBe('updated');
    expect($updatedLog?->attribute_changes->get('attributes')['title'] ?? null)->toBe('Updated Title');
    expect($updatedLog?->attribute_changes->get('old')['title'] ?? null)->toBe('Original Title');

    $post->delete();

    $deletedLog = Activity::query()
        ->where('subject_type', Post::class)
        ->where('event', 'deleted')
        ->latest('id')
        ->first();
    expect($deletedLog?->description)->toBe('post.deleted');
    expect($deletedLog?->event)->toBe('deleted');
});

test('records activity when a Page is created / updated / deleted', function (): void {
    $page = Page::factory()->create(['title' => 'Original Page']);

    $createdLog = Activity::query()
        ->where('subject_type', Page::class)
        ->where('event', 'created')
        ->latest('id')
        ->first();
    expect($createdLog?->description)->toBe('page.created');
    expect($createdLog?->event)->toBe('created');
    expect($createdLog?->subject_type)->toBe(Page::class);
    expect((string) $createdLog?->subject_id)->toBe((string) $page->getKey());

    $createdAttributes = $createdLog?->attribute_changes->get('attributes') ?? [];
    expect($createdAttributes['title'] ?? null)->toBe('Original Page');
    expect($createdAttributes)
        ->not->toHaveKey('body_html')
        ->and($createdAttributes)->not->toHaveKey('excerpt')
        ->and($createdAttributes)->not->toHaveKey('seo_title');

    $page->update(['title' => 'Renamed Page']);

    $updatedLog = Activity::query()
        ->where('subject_type', Page::class)
        ->where('event', 'updated')
        ->latest('id')
        ->first();
    expect($updatedLog?->description)->toBe('page.updated');
    expect($updatedLog?->attribute_changes->get('attributes')['title'] ?? null)->toBe('Renamed Page');
    expect($updatedLog?->attribute_changes->get('old')['title'] ?? null)->toBe('Original Page');

    $page->delete();

    $deletedLog = Activity::query()
        ->where('subject_type', Page::class)
        ->where('event', 'deleted')
        ->latest('id')
        ->first();
    expect($deletedLog?->description)->toBe('page.deleted');
    expect($deletedLog?->event)->toBe('deleted');
});

test('records activity when a User is created / updated and never leaks the password or 2FA fields', function (): void {
    $user = User::factory()->create([
        'name' => 'Alice',
        'email' => 'alice@example.test',
    ]);

    $createdLog = Activity::query()
        ->where('subject_type', User::class)
        ->where('event', 'created')
        ->latest('id')
        ->first();
    expect($createdLog?->description)->toBe('user.created');
    expect($createdLog?->event)->toBe('created');
    expect($createdLog?->subject_type)->toBe(User::class);
    expect((string) $createdLog?->subject_id)->toBe((string) $user->getKey());

    $createdAttributes = $createdLog?->attribute_changes->get('attributes') ?? [];
    expect($createdAttributes['name'] ?? null)->toBe('Alice');
    expect($createdAttributes['email'] ?? null)->toBe('alice@example.test');
    expect($createdAttributes)
        ->not->toHaveKey('password')
        ->and($createdAttributes)->not->toHaveKey('remember_token')
        ->and($createdAttributes)->not->toHaveKey('two_factor_secret')
        ->and($createdAttributes)->not->toHaveKey('two_factor_recovery_codes')
        ->and($createdAttributes)->not->toHaveKey('two_factor_confirmed_at');

    $user->update(['name' => 'Alice Liddell']);

    $updatedLog = Activity::query()
        ->where('subject_type', User::class)
        ->where('event', 'updated')
        ->latest('id')
        ->first();
    expect($updatedLog?->description)->toBe('user.updated');
    expect($updatedLog?->attribute_changes->get('attributes')['name'] ?? null)->toBe('Alice Liddell');
    expect($updatedLog?->attribute_changes->get('old')['name'] ?? null)->toBe('Alice');

    // Double-check no activity row anywhere exposes password-like fields.
    foreach (Activity::query()->where('subject_type', User::class)->get() as $activity) {
        $attributes = $activity->attribute_changes->get('attributes') ?? [];
        $old = $activity->attribute_changes->get('old') ?? [];
        expect($attributes)->not->toHaveKey('password');
        expect($attributes)->not->toHaveKey('remember_token');
        expect($attributes)->not->toHaveKey('two_factor_secret');
        expect($attributes)->not->toHaveKey('two_factor_recovery_codes');
        expect($attributes)->not->toHaveKey('two_factor_confirmed_at');
        expect($old)->not->toHaveKey('password');
        expect($old)->not->toHaveKey('remember_token');
        expect($old)->not->toHaveKey('two_factor_secret');
        expect($old)->not->toHaveKey('two_factor_recovery_codes');
        expect($old)->not->toHaveKey('two_factor_confirmed_at');
    }
});

test('skips logging when only non-whitelisted attributes change on a Post', function (): void {
    $post = Post::factory()->create(['title' => 'Stable Title']);

    $baseline = Activity::query()->where('subject_type', Post::class)->count();

    $post->update(['body_html' => '<p>completely different body</p>']);

    // body_html is not whitelisted and logOnlyDirty + dontLogEmptyChanges should suppress the row.
    expect(Activity::query()->where('subject_type', Post::class)->count())->toBe($baseline);
});
