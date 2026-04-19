<?php

declare(strict_types=1);

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('comments.default_status', 'Pending');
        $this->migrator->add('comments.allow_guests', true);
        $this->migrator->add('comments.max_depth', 3);
        $this->migrator->add('comments.throttle_per_minute', 3);
        $this->migrator->add('comments.notify_author_on_reply', false);
        $this->migrator->add('comments.honeypot_enabled', true);
    }

    public function down(): void
    {
        $this->migrator->deleteIfExists('comments.default_status');
        $this->migrator->deleteIfExists('comments.allow_guests');
        $this->migrator->deleteIfExists('comments.max_depth');
        $this->migrator->deleteIfExists('comments.throttle_per_minute');
        $this->migrator->deleteIfExists('comments.notify_author_on_reply');
        $this->migrator->deleteIfExists('comments.honeypot_enabled');
    }
};
