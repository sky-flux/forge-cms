<?php

declare(strict_types=1);

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

/**
 * Legal / compliance configuration surfaced on public pages.
 *
 * Terms URL, privacy URL, and the cookie banner state/text are exposed via
 * HandleInertiaRequests::share() under the `legal` key so the frontend can
 * render the footer links and cookie banner on every Inertia response.
 *
 * `gdpr_comment_opt_in` is persisted for future comment-form enforcement and
 * is not currently surfaced to the frontend.
 */
class LegalSettings extends Settings
{
    public ?string $terms_url;

    public ?string $privacy_url;

    public bool $cookie_banner_enabled;

    public ?string $cookie_banner_text;

    public bool $gdpr_comment_opt_in;

    public static function group(): string
    {
        return 'legal';
    }
}
