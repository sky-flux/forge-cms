<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;

test('registers the horizon artisan command', function (): void {
    expect(Artisan::all())->toHaveKey('horizon');
});

test('registers the horizon dashboard route at /horizon', function (): void {
    expect(Route::has('horizon.index'))->toBeTrue();
});
