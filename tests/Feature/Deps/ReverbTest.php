<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;

test('registers the reverb:start artisan command', function (): void {
    expect(Artisan::all())->toHaveKey('reverb:start');
});

test('configures a reverb connection in the broadcasting config', function (): void {
    expect(config('broadcasting.connections.reverb'))->toBeArray()
        ->and(config('broadcasting.connections.reverb.driver'))->toBe('reverb');
});
