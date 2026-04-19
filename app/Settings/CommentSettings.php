<?php

declare(strict_types=1);

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

/**
 * Runtime policy for the comment subsystem.
 *
 * - `default_status`, `max_depth`, and `honeypot_enabled` are ENFORCED by the
 *   comment submission pipeline (CommentObserver::creating and
 *   StoreCommentRequest::after).
 * - `allow_guests`, `throttle_per_minute`, and `notify_author_on_reply` are
 *   PERSISTED but NOT YET wired into the runtime — the throttle middleware is
 *   declared statically in routes/web.php (`throttle:3,1`) and the allow-guests
 *   rule still lives in StoreCommentRequest's guest-field validation. These
 *   settings are exposed here so the UI surface lands first; wiring follows in
 *   a later batch once dynamic-throttle middleware is introduced.
 */
class CommentSettings extends Settings
{
    /**
     * CommentStatus enum case NAME (not value): one of 'Pending', 'Approved',
     * or 'Trash'. Stored as the case name so the Filament Select options are
     * human-readable.
     */
    public string $default_status;

    public bool $allow_guests;

    public int $max_depth;

    public int $throttle_per_minute;

    public bool $notify_author_on_reply;

    public bool $honeypot_enabled;

    public static function group(): string
    {
        return 'comments';
    }
}
