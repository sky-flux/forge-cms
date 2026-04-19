<?php

declare(strict_types=1);

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('mail.from_name', null);
        $this->migrator->add('mail.from_address', null);
        $this->migrator->add('mail.reply_to', null);
        $this->migrator->add('mail.footer_template', null);
    }

    public function down(): void
    {
        $this->migrator->deleteIfExists('mail.from_name');
        $this->migrator->deleteIfExists('mail.from_address');
        $this->migrator->deleteIfExists('mail.reply_to');
        $this->migrator->deleteIfExists('mail.footer_template');
    }
};
