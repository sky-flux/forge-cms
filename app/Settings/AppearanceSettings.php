<?php

declare(strict_types=1);

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

/**
 * Public-facing branding and theme configuration.
 *
 * Exposed via HandleInertiaRequests::share() under the `appearance` key so the
 * React shell can embed the logo, favicon, primary color, and footer text on
 * every Inertia response without a round-trip.
 */
class AppearanceSettings extends Settings
{
    public ?string $logo_url;

    public ?string $favicon_url;

    public string $primary_color;

    public ?string $footer_text;

    public static function group(): string
    {
        return 'appearance';
    }
}
