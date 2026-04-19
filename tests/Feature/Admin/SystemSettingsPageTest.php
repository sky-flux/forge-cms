<?php

declare(strict_types=1);

use App\Filament\Pages\SystemSettings;
use App\Models\User;
use App\Settings\AppearanceSettings;
use App\Settings\BackupSettings;
use App\Settings\CommentSettings;
use App\Settings\FeedSettings;
use App\Settings\GeneralSettings;
use App\Settings\LegalSettings;
use App\Settings\MailSettings;
use App\Settings\MediaUploadSettings;
use App\Settings\PerformanceSettings;
use App\Settings\SecuritySettings;
use App\Settings\SeoSettings;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    $this->withoutVite();
    Role::findOrCreate('super_admin');
});

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

test('SystemSettings page lives under 系统 nav group', function (): void {
    expect(SystemSettings::getNavigationGroup())->toBe('系统')
        ->and(SystemSettings::getNavigationSort())->toBe(4)
        ->and(SystemSettings::getNavigationLabel())->toBe('配置');
});

test('super_admin can render the system settings page', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');

    $this->actingAs($admin)
        ->get(SystemSettings::getUrl())
        ->assertSuccessful();
});

test('guests are redirected from the system settings page to login', function (): void {
    $this->get(SystemSettings::getUrl())->assertRedirect('/console/login');
});

test('saving the form persists GeneralSettings values', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');

    Livewire::actingAs($admin)
        ->test(SystemSettings::class)
        ->fillForm([
            'general' => [
                'site_name' => 'Updated Brand',
                'site_description' => 'New description',
                'contact_email' => 'hello@example.com',
                'default_seo_description' => 'New SEO line',
                'default_og_image' => 'https://example.com/og.png',
            ],
            'backup' => app(BackupSettings::class)->toArray(),
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    app()->forgetInstance(GeneralSettings::class);
    $reloaded = app(GeneralSettings::class);

    expect($reloaded->site_name)->toBe('Updated Brand')
        ->and($reloaded->default_og_image)->toBe('https://example.com/og.png');
});

test('a non-super_admin authenticated user cannot access system settings', function (): void {
    Role::findOrCreate('admin');
    $editor = User::factory()->create();
    $editor->assignRole('admin');

    $this->actingAs($editor)
        ->get(SystemSettings::getUrl())
        ->assertForbidden();
});

test('non-super_admin instantiating the SystemSettings Livewire component is rejected', function (): void {
    Role::findOrCreate('admin');
    $editor = User::factory()->create();
    $editor->assignRole('admin');

    Livewire::actingAs($editor)
        ->test(SystemSettings::class)
        ->assertForbidden();
});

test('system settings page saves backup tab values', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');

    Livewire::actingAs($admin)
        ->test(SystemSettings::class)
        ->fillForm([
            'general' => app(GeneralSettings::class)->toArray(),
            'backup' => [
                'enabled' => true,
                'destination_disk' => 'local',
                'include_storage_files' => false,
                'keep_daily_days' => 14,
                'keep_weekly_weeks' => 8,
                'keep_monthly_months' => 6,
                'notify_email' => 'ops@example.com',
                'schedule_hour' => 3,
            ],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    app()->forgetInstance(BackupSettings::class);
    $reloaded = app(BackupSettings::class);
    expect($reloaded->enabled)->toBeTrue()
        ->and($reloaded->keep_daily_days)->toBe(14)
        ->and($reloaded->notify_email)->toBe('ops@example.com')
        ->and($reloaded->schedule_hour)->toBe(3);
});

test('system settings page saves seo tab', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');

    Livewire::actingAs($admin)
        ->test(SystemSettings::class)
        ->fillForm([
            'seo' => [
                'google_analytics_id' => 'G-XYZ',
                'twitter_site_handle' => '@forge',
                'sitemap_include_categories' => false,
                'sitemap_include_tags' => false,
            ],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    app()->forgetInstance(SeoSettings::class);
    $reloaded = app(SeoSettings::class);
    expect($reloaded->google_analytics_id)->toBe('G-XYZ')
        ->and($reloaded->twitter_site_handle)->toBe('@forge')
        ->and($reloaded->sitemap_include_categories)->toBeFalse();
});

test('system settings page saves comment policy tab', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');

    Livewire::actingAs($admin)
        ->test(SystemSettings::class)
        ->fillForm([
            'comments' => [
                'default_status' => 'Approved',
                'allow_guests' => false,
                'max_depth' => 2,
                'throttle_per_minute' => 10,
                'notify_author_on_reply' => true,
                'honeypot_enabled' => false,
            ],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    app()->forgetInstance(CommentSettings::class);
    $reloaded = app(CommentSettings::class);
    expect($reloaded->default_status)->toBe('Approved')
        ->and($reloaded->allow_guests)->toBeFalse()
        ->and($reloaded->max_depth)->toBe(2);
});

test('system settings page saves media upload + feed tabs', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');

    Livewire::actingAs($admin)
        ->test(SystemSettings::class)
        ->fillForm([
            'media_upload' => [
                'max_upload_size_mb' => 25,
                'allowed_mime_types_csv' => 'image/jpeg,image/png',
                'auto_convert_to_webp' => true,
                'image_quality' => 70,
            ],
            'feed' => [
                'items_per_feed' => 100,
                'feed_cache_ttl_minutes' => 30,
                'include_excerpts_in_feed' => false,
                'sitemap_default_priority' => 0.8,
                'sitemap_change_frequency' => 'daily',
            ],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    app()->forgetInstance(MediaUploadSettings::class);
    app()->forgetInstance(FeedSettings::class);
    expect(app(MediaUploadSettings::class)->image_quality)->toBe(70)
        ->and(app(MediaUploadSettings::class)->auto_convert_to_webp)->toBeTrue()
        ->and(app(FeedSettings::class)->items_per_feed)->toBe(100)
        ->and(app(FeedSettings::class)->sitemap_default_priority)->toBe(0.8)
        ->and(app(FeedSettings::class)->sitemap_change_frequency)->toBe('daily');
});

test('system settings page saves mail/appearance/performance/legal/security tabs', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');

    Livewire::actingAs($admin)
        ->test(SystemSettings::class)
        ->fillForm([
            'mail' => ['from_name' => 'Forge', 'from_address' => 'noreply@ex.com', 'reply_to' => null, 'footer_template' => 'Sent by Forge'],
            'appearance' => ['logo_url' => 'https://ex.com/logo.png', 'favicon_url' => null, 'primary_color' => '#ff0000', 'footer_text' => '© Forge'],
            'performance' => ['post_cache_ttl_minutes' => 120, 'sitemap_cache_ttl_hours' => 48, 'scout_batch_size' => 1000],
            'legal' => ['terms_url' => 'https://ex.com/terms', 'privacy_url' => null, 'cookie_banner_enabled' => true, 'cookie_banner_text' => 'We use cookies', 'gdpr_comment_opt_in' => true],
            'security' => ['require_2fa_for_super_admin' => true, 'session_lifetime_minutes' => 60, 'password_min_length' => 16, 'max_login_attempts' => 3, 'lockout_minutes' => 30],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    foreach ([MailSettings::class, AppearanceSettings::class, PerformanceSettings::class, LegalSettings::class, SecuritySettings::class] as $cls) {
        app()->forgetInstance($cls);
    }

    expect(app(MailSettings::class)->from_name)->toBe('Forge')
        ->and(app(AppearanceSettings::class)->primary_color)->toBe('#ff0000')
        ->and(app(PerformanceSettings::class)->post_cache_ttl_minutes)->toBe(120)
        ->and(app(LegalSettings::class)->cookie_banner_enabled)->toBeTrue()
        ->and(app(SecuritySettings::class)->require_2fa_for_super_admin)->toBeTrue();
});

test('public pages expose appearance + legal shared props', function (): void {
    $a = app(AppearanceSettings::class);
    $a->logo_url = 'https://ex.com/logo.png';
    $a->save();

    $l = app(LegalSettings::class);
    $l->cookie_banner_enabled = true;
    $l->cookie_banner_text = 'We use cookies';
    $l->save();

    $this->withoutVite();

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('appearance.logo_url', 'https://ex.com/logo.png')
            ->where('legal.cookie_banner_enabled', true)
            ->where('legal.cookie_banner_text', 'We use cookies')
        );
});
