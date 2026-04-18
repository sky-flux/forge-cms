<?php

declare(strict_types=1);

use Laravel\Scout\EngineManager;
use Meilisearch\Client as MeiliClient;

test('has scout bound in the service container with a meilisearch driver class loadable', function (): void {
    expect(app(EngineManager::class))->toBeInstanceOf(EngineManager::class);
    expect(class_exists(MeiliClient::class))->toBeTrue();
});

test('scout driver is configurable via env (meilisearch in prod, collection under phpunit)', function (): void {
    // phpunit.xml sets SCOUT_DRIVER=collection so tests run against Scout's in-memory engine.
    // .env sets SCOUT_DRIVER=meilisearch for the real app.
    expect(config('scout.driver'))->toBe('collection');
});
