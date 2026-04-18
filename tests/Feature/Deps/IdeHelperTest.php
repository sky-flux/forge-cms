<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;

test('registers ide-helper artisan commands', function (): void {
    expect(Artisan::all())
        ->toHaveKey('ide-helper:generate')
        ->toHaveKey('ide-helper:models');
});
