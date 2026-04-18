<?php

declare(strict_types=1);

use Spatie\Feed\FeedServiceProvider;

test('registers the feed service provider in the application', function (): void {
    expect(app()->getLoadedProviders())->toHaveKey(FeedServiceProvider::class);
});

test('ships the feed config with a feeds array', function (): void {
    expect(config('feed.feeds'))->toBeArray();
});
