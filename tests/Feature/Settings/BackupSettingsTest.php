<?php

declare(strict_types=1);

use App\Settings\BackupSettings;

test('BackupSettings resolves with safe defaults', function (): void {
    $s = app(BackupSettings::class);
    expect($s->enabled)->toBeFalse()
        ->and($s->destination_disk)->toBe('local')
        ->and($s->include_storage_files)->toBeTrue()
        ->and($s->keep_daily_days)->toBe(7)
        ->and($s->keep_weekly_weeks)->toBe(4)
        ->and($s->keep_monthly_months)->toBe(3)
        ->and($s->notify_email)->toBeNull()
        ->and($s->schedule_hour)->toBe(2);
});

test('BackupSettings persists and reloads across resolves', function (): void {
    $s = app(BackupSettings::class);
    $s->enabled = true;
    $s->destination_disk = 'local';
    $s->notify_email = 'ops@example.com';
    $s->save();

    app()->forgetInstance(BackupSettings::class);
    $reloaded = app(BackupSettings::class);
    expect($reloaded->enabled)->toBeTrue()
        ->and($reloaded->notify_email)->toBe('ops@example.com');
});
