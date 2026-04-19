<?php

declare(strict_types=1);

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('performance.post_cache_ttl_minutes', 60);
        $this->migrator->add('performance.sitemap_cache_ttl_hours', 24);
        $this->migrator->add('performance.scout_batch_size', 500);
    }

    public function down(): void
    {
        $this->migrator->deleteIfExists('performance.post_cache_ttl_minutes');
        $this->migrator->deleteIfExists('performance.sitemap_cache_ttl_hours');
        $this->migrator->deleteIfExists('performance.scout_batch_size');
    }
};
