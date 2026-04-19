<?php

declare(strict_types=1);

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

/**
 * RSS feed + sitemap runtime configuration.
 *
 * - `items_per_feed` is ENFORCED by Post::getFeedItems() to limit how many
 *   posts the main RSS feed exposes.
 * - `include_excerpts_in_feed` is ENFORCED by Post::toFeedItem() — when
 *   disabled, the feed item summary falls back to the title alone.
 * - `sitemap_default_priority` and `sitemap_change_frequency` are ENFORCED
 *   by SitemapController and applied to every <url> entry.
 * - `feed_cache_ttl_minutes` is ENFORCED by SitemapController (wraps the
 *   rendered XML in Cache::remember when > 0). The RSS feed uses
 *   spatie/laravel-feed which does not expose a native cache hook in this
 *   install, so the TTL is only honored for the sitemap surface.
 */
class FeedSettings extends Settings
{
    public int $items_per_feed;

    public int $feed_cache_ttl_minutes;

    public bool $include_excerpts_in_feed;

    public float $sitemap_default_priority;

    /**
     * One of: always|hourly|daily|weekly|monthly|yearly|never.
     */
    public string $sitemap_change_frequency;

    public static function group(): string
    {
        return 'feed';
    }
}
