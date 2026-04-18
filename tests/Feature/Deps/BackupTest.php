<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;

test('registers the backup:run, backup:clean, and backup:list artisan commands', function (): void {
    expect(Artisan::all())
        ->toHaveKey('backup:run')
        ->toHaveKey('backup:clean')
        ->toHaveKey('backup:list');
});
