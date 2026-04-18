<?php

declare(strict_types=1);
use Larastan\Larastan\Properties\ModelPropertyExtension;

test('ships the phpstan.neon config and larastan extension is loadable', function (): void {
    expect(file_exists(base_path('phpstan.neon')))->toBeTrue();
    expect(class_exists(ModelPropertyExtension::class)
        || class_exists(NunoMaduro\Larastan\Properties\ModelPropertyExtension::class))
        ->toBeTrue();
});
