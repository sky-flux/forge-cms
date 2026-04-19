<?php

declare(strict_types=1);

use App\Enums\PostStatus;
use App\Models\Category;
use App\Models\Page;
use App\Models\Post;
use App\Models\Tag;
use App\Settings\FeedSettings;
use App\Settings\SeoSettings;

test('sitemap.xml returns a valid sitemap covering home, posts, pages, categories, tags', function (): void {
    $post = Post::factory()->published()->create();
    $page = Page::factory()->published()->create(['slug' => 'about']);
    $category = Category::factory()->create(['slug' => 'news']);
    $tag = Tag::factory()->create(['slug' => 'laravel']);

    $response = $this->get('/sitemap.xml');

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('xml');

    $body = $response->getContent();
    expect($body)->toContain('<urlset')
        ->and($body)->toContain(url('/'))
        ->and($body)->toContain(route('posts.index'))
        ->and($body)->toContain(route('posts.show', ['post' => $post]))
        ->and($body)->toContain(route('pages.show', ['page' => $page]))
        ->and($body)->toContain(route('categories.show', ['category' => $category]))
        ->and($body)->toContain(route('tags.show', ['tag' => $tag]));
});

test('sitemap.xml excludes draft posts and pages', function (): void {
    $draftPost = Post::factory()->create(['status' => PostStatus::Draft, 'slug' => 'draft-post']);
    $draftPage = Page::factory()->create(['status' => PostStatus::Draft, 'slug' => 'draft-page']);

    $body = $this->get('/sitemap.xml')->getContent();

    expect($body)->not->toContain(route('posts.show', ['post' => $draftPost]))
        ->and($body)->not->toContain(route('pages.show', ['page' => $draftPage]));
});

test('sitemap honors SeoSettings sitemap_include_categories toggle', function (): void {
    $category = Category::factory()->create(['slug' => 'news-toggle']);

    $s = app(SeoSettings::class);
    $s->sitemap_include_categories = false;
    $s->save();

    $body = $this->get('/sitemap.xml')->getContent();
    expect($body)->not->toContain(route('categories.show', ['category' => $category]));
});

test('sitemap honors SeoSettings sitemap_include_tags toggle', function (): void {
    $tag = Tag::factory()->create(['slug' => 'laravel-toggle']);

    $s = app(SeoSettings::class);
    $s->sitemap_include_tags = false;
    $s->save();

    $body = $this->get('/sitemap.xml')->getContent();
    expect($body)->not->toContain(route('tags.show', ['tag' => $tag]));
});

test('sitemap urls carry FeedSettings priority + changefreq', function (): void {
    Post::factory()->published()->create();

    $s = app(FeedSettings::class);
    $s->sitemap_default_priority = 0.9;
    $s->sitemap_change_frequency = 'daily';
    $s->save();

    $body = $this->get('/sitemap.xml')->getContent();
    expect($body)->toContain('<priority>0.9</priority>')
        ->and($body)->toContain('<changefreq>daily</changefreq>');
});
