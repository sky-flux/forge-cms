<?php

declare(strict_types=1);

test('every seeder file declares strict types', function (): void {
    $missing = [];

    foreach (glob(base_path('database/seeders/*.php')) ?: [] as $file) {
        if (! str_contains(file_get_contents($file), 'declare(strict_types=1);')) {
            $missing[] = basename($file);
        }
    }

    expect($missing)->toBe([], 'Seeders missing strict_types: '.implode(', ', $missing));
});
