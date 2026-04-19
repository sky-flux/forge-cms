<?php

declare(strict_types=1);

use App\Settings\GeneralSettings;
use Illuminate\Support\Facades\Schema;

test('settings table exists after install', function (): void {
    expect(Schema::hasTable('settings'))->toBeTrue()
        ->and(Schema::hasColumns('settings', ['group', 'name', 'payload', 'locked']))->toBeTrue();
});

test('GeneralSettings is resolvable from the container with defaults', function (): void {
    $settings = app(GeneralSettings::class);

    expect($settings->site_name)->toBeString()->not->toBeEmpty()
        ->and($settings->site_description)->toBeString()
        ->and($settings->contact_email)->toBeString()
        ->and($settings->default_seo_description)->toBeString()
        ->and($settings->default_og_image)->toBeNull();
});

test('writing to GeneralSettings persists across resolves', function (): void {
    $settings = app(GeneralSettings::class);
    $settings->site_name = 'New Forge Brand';
    $settings->save();

    app()->forgetInstance(GeneralSettings::class);

    expect(app(GeneralSettings::class)->site_name)->toBe('New Forge Brand');
});
