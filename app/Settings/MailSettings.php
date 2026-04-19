<?php

declare(strict_types=1);

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

/**
 * Outbound mail identity configuration surfaced in the admin SystemSettings
 * page.
 *
 * MVP: persisted; runtime wiring pending. Admins can configure the expected
 * from-name, from-address, reply-to, and footer template ahead of the mailer
 * integration that will consume these values in a future task.
 */
class MailSettings extends Settings
{
    public ?string $from_name;

    public ?string $from_address;

    public ?string $reply_to;

    public ?string $footer_template;

    public static function group(): string
    {
        return 'mail';
    }
}
