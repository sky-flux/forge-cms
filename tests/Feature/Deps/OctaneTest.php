<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;

test('registers the octane:start artisan command', function (): void {
    expect(Artisan::all())->toHaveKey('octane:start');
});

test('configures frankenphp as the default octane server', function (): void {
    expect(config('octane.server'))->toBe('frankenphp');
});
