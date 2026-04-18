<?php

declare(strict_types=1);

use Spatie\Honeypot\ProtectAgainstSpam;

test('exposes the honeypot middleware for route protection', function (): void {
    expect(class_exists(ProtectAgainstSpam::class))->toBeTrue();
    expect(config('honeypot.enabled'))->toBeTrue();
});
