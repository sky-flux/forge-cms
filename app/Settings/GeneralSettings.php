<?php

declare(strict_types=1);

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class GeneralSettings extends Settings
{
    public string $site_name;

    public string $site_description;

    public string $contact_email;

    public string $default_seo_description;

    public ?string $default_og_image;

    public static function group(): string
    {
        return 'general';
    }
}
