<?php

declare(strict_types=1);

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('general.site_name', 'ForgeCMS');
        $this->migrator->add('general.site_description', 'A Laravel + Filament + Inertia content management system.');
        $this->migrator->add('general.contact_email', 'admin@example.com');
        $this->migrator->add('general.default_seo_description', 'Built with ForgeCMS.');
        $this->migrator->add('general.default_og_image', null);
    }

    public function down(): void
    {
        $this->migrator->deleteIfExists('general.site_name');
        $this->migrator->deleteIfExists('general.site_description');
        $this->migrator->deleteIfExists('general.contact_email');
        $this->migrator->deleteIfExists('general.default_seo_description');
        $this->migrator->deleteIfExists('general.default_og_image');
    }
};
