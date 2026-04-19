<?php

declare(strict_types=1);

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('seo.google_analytics_id', null);
        $this->migrator->add('seo.google_tag_manager_id', null);
        $this->migrator->add('seo.google_site_verification', null);
        $this->migrator->add('seo.bing_site_verification', null);
        $this->migrator->add('seo.twitter_site_handle', null);
        $this->migrator->add('seo.facebook_app_id', null);
        $this->migrator->add('seo.robots_extra', null);
        $this->migrator->add('seo.sitemap_include_categories', true);
        $this->migrator->add('seo.sitemap_include_tags', true);
    }

    public function down(): void
    {
        $this->migrator->deleteIfExists('seo.google_analytics_id');
        $this->migrator->deleteIfExists('seo.google_tag_manager_id');
        $this->migrator->deleteIfExists('seo.google_site_verification');
        $this->migrator->deleteIfExists('seo.bing_site_verification');
        $this->migrator->deleteIfExists('seo.twitter_site_handle');
        $this->migrator->deleteIfExists('seo.facebook_app_id');
        $this->migrator->deleteIfExists('seo.robots_extra');
        $this->migrator->deleteIfExists('seo.sitemap_include_categories');
        $this->migrator->deleteIfExists('seo.sitemap_include_tags');
    }
};
