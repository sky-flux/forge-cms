<?php

declare(strict_types=1);

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class BackupSettings extends Settings
{
    public bool $enabled;

    public string $destination_disk;

    public bool $include_storage_files;

    public int $keep_daily_days;

    public int $keep_weekly_weeks;

    public int $keep_monthly_months;

    public ?string $notify_email;

    public int $schedule_hour;

    public static function group(): string
    {
        return 'backup';
    }
}
