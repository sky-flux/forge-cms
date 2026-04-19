<?php

declare(strict_types=1);

use App\Models\Post;

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
