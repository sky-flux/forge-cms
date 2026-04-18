<?php

declare(strict_types=1);

use Spatie\Activitylog\Models\Activity;
use Spatie\Activitylog\Models\Concerns\LogsActivity;

test('records an activity entry for a logged event', function (): void {
    activity()->log('test event');

    expect(Activity::query()->latest()->first()?->description)->toBe('test event');
});

test('exposes the LogsActivity trait for opting models into activity logging', function (): void {
    expect(trait_exists(LogsActivity::class))->toBeTrue();
});
