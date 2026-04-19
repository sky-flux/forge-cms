<?php

declare(strict_types=1);

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('appearance.logo_url', null);
        $this->migrator->add('appearance.favicon_url', null);
        $this->migrator->add('appearance.primary_color', '#1e40af');
        $this->migrator->add('appearance.footer_text', null);
    }

    public function down(): void
    {
        $this->migrator->deleteIfExists('appearance.logo_url');
        $this->migrator->deleteIfExists('appearance.favicon_url');
        $this->migrator->deleteIfExists('appearance.primary_color');
        $this->migrator->deleteIfExists('appearance.footer_text');
    }
};
