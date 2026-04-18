<?php

declare(strict_types=1);
use Rector\Config\RectorConfig;
use RectorLaravel\Set\LaravelSetList;

test('ships the rector config and base classes load', function (): void {
    expect(file_exists(base_path('rector.php')))->toBeTrue();
    expect(class_exists(RectorConfig::class))->toBeTrue();
    expect(class_exists(LaravelSetList::class))->toBeTrue();
});
