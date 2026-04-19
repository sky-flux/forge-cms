<?php

declare(strict_types=1);

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('security.require_2fa_for_super_admin', false);
        $this->migrator->add('security.session_lifetime_minutes', 120);
        $this->migrator->add('security.password_min_length', 12);
        $this->migrator->add('security.max_login_attempts', 5);
        $this->migrator->add('security.lockout_minutes', 15);
    }

    public function down(): void
    {
        $this->migrator->deleteIfExists('security.require_2fa_for_super_admin');
        $this->migrator->deleteIfExists('security.session_lifetime_minutes');
        $this->migrator->deleteIfExists('security.password_min_length');
        $this->migrator->deleteIfExists('security.max_login_attempts');
        $this->migrator->deleteIfExists('security.lockout_minutes');
    }
};
