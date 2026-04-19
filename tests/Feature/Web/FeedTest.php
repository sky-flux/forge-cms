<?php

declare(strict_types=1);

use App\Models\Post;
use App\Settings\FeedSettings;

test('feed.xml returns RSS containing published post titles', function (): void {
    $published = Post::factory()->published()->create(['title' => 'RSS Sample Post']);
    Post::factory()->create(['title' => 'Draft Post Should Not Appear']);

    $response = $this->get('/feed.xml');

    $response->assertOk();

    expect($response->headers->get('Content-Type'))->toContain('xml');

    $body = $response->getContent();

    expect($body)
        ->toContain('<rss')
        ->toContain($published->title)
        ->not->toContain('Draft Post Should Not Appear');
});

test('feed honors FeedSettings::items_per_feed limit', function (): void {
    $s = app(FeedSettings::class);
    $s->items_per_feed = 2;
    $s->save();

    Post::factory()->count(5)->published()->create();

    $body = $this->get('/feed.xml')->getContent();
    // Count <item> occurrences — spatie feed uses <item> for RSS
    expect(substr_count($body, '<item>'))->toBe(2);
});
