<?php

declare(strict_types=1);

use App\Enums\PostStatus;
use App\Models\Post;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

test('PostStatus enum exposes draft, published, scheduled cases with labels', function (): void {
    expect(PostStatus::Draft->value)->toBe('draft');
    expect(PostStatus::Published->value)->toBe('published');
    expect(PostStatus::Scheduled->value)->toBe('scheduled');
    expect(PostStatus::Draft->label())->toBe('草稿');
});

test('a post can be created via the factory and has a uuid + route key', function (): void {
    $post = Post::factory()->create();

    expect($post->uuid)->toBeString()->not->toBeEmpty();
    expect($post->getRouteKeyName())->toBe('uuid');
    expect($post->status)->toBe(PostStatus::Draft);
    expect($post->is_comments_enabled)->toBeTrue();
});

test('published scope returns only posts with status=published and published_at in the past', function (): void {
    $published = Post::factory()->published()->create();
    Post::factory()->create(); // draft
    Post::factory()->scheduled()->create();

    $found = Post::published()->get();

    expect($found)->toHaveCount(1)
        ->and($found->first()->is($published))->toBeTrue();
});

test('draft scope returns only draft posts', function (): void {
    Post::factory()->published()->create();
    $draft = Post::factory()->create();

    $found = Post::draft()->get();

    expect($found)->toHaveCount(1)
        ->and($found->first()->is($draft))->toBeTrue();
});

test('post soft-deletes and can be restored', function (): void {
    $post = Post::factory()->create();
    $post->delete();

    expect(Post::count())->toBe(0);
    expect(Post::withTrashed()->count())->toBe(1);

    $post->restore();

    expect(Post::count())->toBe(1);
});

test('post belongs to its author', function (): void {
    $author = User::factory()->create();
    $post = Post::factory()->for($author)->create();

    expect($post->user->is($author))->toBeTrue();
});

test('slug is auto-generated from title on save', function (): void {
    $post = Post::factory()->create(['title' => 'Hello World Post']);

    expect($post->slug)->toBe('hello-world-post');
});

test('slug is unique — duplicates get a suffix', function (): void {
    Post::factory()->create(['title' => 'Same Title']);
    $second = Post::factory()->create(['title' => 'Same Title']);

    expect($second->slug)->not->toBe('same-title');
    expect($second->slug)->toStartWith('same-title-');
});

test('published post is searchable and exposes key fields in toSearchableArray', function (): void {
    $post = Post::factory()->published()->create([
        'title' => 'Searchable Title',
        'body_html' => '<p>body text</p>',
    ]);

    expect($post->shouldBeSearchable())->toBeTrue();

    $array = $post->toSearchableArray();
    expect($array)->toHaveKeys(['id', 'uuid', 'title', 'excerpt', 'body_html', 'status', 'published_at'])
        ->and($array['title'])->toBe('Searchable Title')
        ->and($array['body_html'])->toBe('body text'); // strip_tags
});

test('draft post is not searchable', function (): void {
    $post = Post::factory()->create(); // draft by default

    expect($post->shouldBeSearchable())->toBeFalse();
});

test('post accepts media uploads to featured and gallery collections', function (): void {
    Storage::fake('public');

    $post = Post::factory()->create();

    $post->addMedia(UploadedFile::fake()->image('cover.png'))
        ->toMediaCollection('featured');
    $post->addMedia(UploadedFile::fake()->image('shot1.png'))
        ->toMediaCollection('gallery');
    $post->addMedia(UploadedFile::fake()->image('shot2.png'))
        ->toMediaCollection('gallery');

    expect($post->fresh()->getMedia('featured'))->toHaveCount(1);
    expect($post->fresh()->getMedia('gallery'))->toHaveCount(2);
});
