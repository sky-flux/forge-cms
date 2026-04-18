<?php

declare(strict_types=1);

use Spatie\Sitemap\Sitemap;
use Spatie\Sitemap\Tags\Url;

test('can build a sitemap with at least one url', function (): void {
    $sitemap = Sitemap::create()->add(Url::create('https://forge-cms.localhost/'));

    expect($sitemap->render())->toContain('<loc>https://forge-cms.localhost/</loc>');
});
