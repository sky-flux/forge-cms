<?php

declare(strict_types=1);

use App\Settings\SeoSettings;

test('robots.txt includes sitemap and custom extra', function (): void {
    $s = app(SeoSettings::class);
    $s->robots_extra = "Disallow: /admin\nDisallow: /console";
    $s->save();

    $response = $this->get('/robots.txt');
    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('text/plain');

    $body = $response->getContent();
    expect($body)->toContain('User-agent: *')
        ->and($body)->toContain('Sitemap: ')
        ->and($body)->toContain('Disallow: /admin');
});
