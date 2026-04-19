<?php

declare(strict_types=1);

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('backup.enabled', false);
        $this->migrator->add('backup.destination_disk', 'local');
        $this->migrator->add('backup.include_storage_files', true);
        $this->migrator->add('backup.keep_daily_days', 7);
        $this->migrator->add('backup.keep_weekly_weeks', 4);
        $this->migrator->add('backup.keep_monthly_months', 3);
        $this->migrator->add('backup.notify_email', null);
        $this->migrator->add('backup.schedule_hour', 2);
    }

    public function down(): void
    {
        $this->migrator->deleteIfExists('backup.enabled');
        $this->migrator->deleteIfExists('backup.destination_disk');
        $this->migrator->deleteIfExists('backup.include_storage_files');
        $this->migrator->deleteIfExists('backup.keep_daily_days');
        $this->migrator->deleteIfExists('backup.keep_weekly_weeks');
        $this->migrator->deleteIfExists('backup.keep_monthly_months');
        $this->migrator->deleteIfExists('backup.notify_email');
        $this->migrator->deleteIfExists('backup.schedule_hour');
    }
};
