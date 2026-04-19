<?php

declare(strict_types=1);

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('feed.items_per_feed', 50);
        $this->migrator->add('feed.feed_cache_ttl_minutes', 60);
        $this->migrator->add('feed.include_excerpts_in_feed', true);
        $this->migrator->add('feed.sitemap_default_priority', 0.5);
        $this->migrator->add('feed.sitemap_change_frequency', 'weekly');
    }

    public function down(): void
    {
        $this->migrator->deleteIfExists('feed.items_per_feed');
        $this->migrator->deleteIfExists('feed.feed_cache_ttl_minutes');
        $this->migrator->deleteIfExists('feed.include_excerpts_in_feed');
        $this->migrator->deleteIfExists('feed.sitemap_default_priority');
        $this->migrator->deleteIfExists('feed.sitemap_change_frequency');
    }
};
