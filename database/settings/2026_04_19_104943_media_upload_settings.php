<?php

declare(strict_types=1);

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('media_upload.max_upload_size_mb', 10);
        $this->migrator->add('media_upload.allowed_mime_types_csv', 'image/jpeg,image/png,image/gif,image/webp,application/pdf');
        $this->migrator->add('media_upload.auto_convert_to_webp', false);
        $this->migrator->add('media_upload.image_quality', 85);
    }

    public function down(): void
    {
        $this->migrator->deleteIfExists('media_upload.max_upload_size_mb');
        $this->migrator->deleteIfExists('media_upload.allowed_mime_types_csv');
        $this->migrator->deleteIfExists('media_upload.auto_convert_to_webp');
        $this->migrator->deleteIfExists('media_upload.image_quality');
    }
};
