<?php

declare(strict_types=1);

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

/**
 * SEO + analytics configuration surfaced across the frontend and XML surfaces.
 *
 * - Third-party analytics IDs (`google_analytics_id`, `google_tag_manager_id`)
 *   and verification tokens (`google_site_verification`, `bing_site_verification`)
 *   are EXPOSED to every Inertia response through HandleInertiaRequests::share()
 *   under the `seo` key, so the React shell can embed the relevant `<script>`
 *   and `<meta>` tags without a round-trip.
 * - `twitter_site_handle` and `facebook_app_id` feed Open Graph / Twitter Card
 *   meta tags on public pages via the same shared prop.
 * - `robots_extra` is appended to the dynamic `/robots.txt` route output.
 * - `sitemap_include_categories` / `sitemap_include_tags` are ENFORCED by
 *   SitemapController::__invoke() — toggling them off skips the corresponding
 *   URL sets from the generated `<urlset>`.
 */
class SeoSettings extends Settings
{
    public ?string $google_analytics_id;

    public ?string $google_tag_manager_id;

    public ?string $google_site_verification;

    public ?string $bing_site_verification;

    public ?string $twitter_site_handle;

    public ?string $facebook_app_id;

    public ?string $robots_extra;

    public bool $sitemap_include_categories;

    public bool $sitemap_include_tags;

    public static function group(): string
    {
        return 'seo';
    }
}
