<?php

declare(strict_types=1);

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

/**
 * Runtime cache + indexing throughput knobs.
 *
 * MVP: persisted; runtime wiring pending. The eventual post / sitemap caches
 * and Scout indexing job will consume these values in a future task — for now
 * the Tab persists them so operators can document intended tuning.
 */
class PerformanceSettings extends Settings
{
    public int $post_cache_ttl_minutes;

    public int $sitemap_cache_ttl_hours;

    public int $scout_batch_size;

    public static function group(): string
    {
        return 'performance';
    }
}
