<?php

declare(strict_types=1);

use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

test('exposes the HasSlug trait and SlugOptions builder', function (): void {
    expect(trait_exists(HasSlug::class))->toBeTrue();
    expect(class_exists(SlugOptions::class))->toBeTrue();
    expect(SlugOptions::create()->generateSlugsFrom('title')->saveSlugsTo('slug'))
        ->toBeInstanceOf(SlugOptions::class);
});
