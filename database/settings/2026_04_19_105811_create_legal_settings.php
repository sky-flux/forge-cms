<?php

declare(strict_types=1);

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('legal.terms_url', null);
        $this->migrator->add('legal.privacy_url', null);
        $this->migrator->add('legal.cookie_banner_enabled', false);
        $this->migrator->add('legal.cookie_banner_text', null);
        $this->migrator->add('legal.gdpr_comment_opt_in', false);
    }

    public function down(): void
    {
        $this->migrator->deleteIfExists('legal.terms_url');
        $this->migrator->deleteIfExists('legal.privacy_url');
        $this->migrator->deleteIfExists('legal.cookie_banner_enabled');
        $this->migrator->deleteIfExists('legal.cookie_banner_text');
        $this->migrator->deleteIfExists('legal.gdpr_comment_opt_in');
    }
};
