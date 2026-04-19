<?php

declare(strict_types=1);

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

/**
 * Media upload policy surfaced in the admin SystemSettings page.
 *
 * MVP: persisted but not enforced; validation layer pending. These values
 * will feed into an upload-validation middleware / Filament form component
 * in a future task — for now the Tab persists them so admins can configure
 * expected limits ahead of the enforcement work.
 *
 * - `max_upload_size_mb`: hard cap in megabytes (range 1-500)
 * - `allowed_mime_types_csv`: comma-separated MIME whitelist
 * - `auto_convert_to_webp`: toggle re-encoding raster images to WebP
 * - `image_quality`: WebP/JPEG quality (1-100)
 */
class MediaUploadSettings extends Settings
{
    public int $max_upload_size_mb;

    public string $allowed_mime_types_csv;

    public bool $auto_convert_to_webp;

    public int $image_quality;

    public static function group(): string
    {
        return 'media_upload';
    }
}
