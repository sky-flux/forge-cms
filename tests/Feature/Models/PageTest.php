<?php

declare(strict_types=1);

use App\Enums\PostStatus;
use App\Models\Page;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

test('a page can be created via the factory with uuid + slug', function (): void {
    $page = Page::factory()->create();

    expect($page->uuid)->toBeString()->not->toBeEmpty();
    expect($page->slug)->toBeString()->not->toBeEmpty();
    expect($page->getRouteKeyName())->toBe('slug');
    expect($page->status)->toBe(PostStatus::Draft);
    expect($page->is_comments_enabled)->toBeTrue();
    expect($page->is_homepage)->toBeFalse();
});

test('slug is auto-generated from title on save', function (): void {
    $page = Page::factory()->create(['title' => 'About Our Team']);

    expect($page->slug)->toBe('about-our-team');
});

test('published scope returns only status=published pages with past published_at', function (): void {
    Page::factory()->published()->create();
    Page::factory()->create(); // draft

    $found = Page::published()->get();

    expect($found)->toHaveCount(1);
});

test('homepage scope filters to is_homepage=true', function (): void {
    Page::factory()->homepage()->create();
    Page::factory()->create();

    $found = Page::homepage()->get();

    expect($found)->toHaveCount(1);
});

test('page soft-deletes and can be restored', function (): void {
    $page = Page::factory()->create();
    $page->delete();

    expect(Page::count())->toBe(0);
    expect(Page::withTrashed()->count())->toBe(1);

    $page->restore();

    expect(Page::count())->toBe(1);
});

test('page accepts media upload to featured collection', function (): void {
    Storage::fake('public');
    $page = Page::factory()->create();

    $page->addMedia(UploadedFile::fake()->image('hero.png'))
        ->toMediaCollection('featured');

    expect($page->fresh()->getMedia('featured'))->toHaveCount(1);
});

test('page belongs to its author', function (): void {
    $author = User::factory()->create();
    $page = Page::factory()->for($author)->create();

    expect($page->user->is($author))->toBeTrue();
});
