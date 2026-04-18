<?php

declare(strict_types=1);

use Laravel\Scout\EngineManager;
use Meilisearch\Client as MeiliClient;

test('has scout bound in the service container with a meilisearch driver class loadable', function (): void {
    expect(app(EngineManager::class))->toBeInstanceOf(EngineManager::class);
    expect(class_exists(MeiliClient::class))->toBeTrue();
});

test('defaults the scout driver to meilisearch per config', function (): void {
    expect(config('scout.driver'))->toBe('meilisearch');
});
