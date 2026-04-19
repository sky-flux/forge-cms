<?php

declare(strict_types=1);

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

/**
 * Authentication hardening policy.
 *
 * MVP: persisted; runtime wiring pending. Fortify password rules, session
 * lifetime, 2FA enforcement for super_admin, and lockout throttling will be
 * wired to these values in a future task.
 */
class SecuritySettings extends Settings
{
    public bool $require_2fa_for_super_admin;

    public int $session_lifetime_minutes;

    public int $password_min_length;

    public int $max_login_attempts;

    public int $lockout_minutes;

    public static function group(): string
    {
        return 'security';
    }
}
