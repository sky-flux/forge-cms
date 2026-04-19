<?php

declare(strict_types=1);

use App\Filament\Pages\SystemSettings;
use App\Models\User;
use App\Settings\GeneralSettings;
use Illuminate\Support\Facades\Schema;
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
    $this->get(SystemSettings::getUrl())->assertRedirect('/admin/login');
});

test('saving the form persists GeneralSettings values', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');

    Livewire::actingAs($admin)
        ->test(SystemSettings::class)
        ->fillForm([
            'site_name' => 'Updated Brand',
            'site_description' => 'New description',
            'contact_email' => 'hello@example.com',
            'default_seo_description' => 'New SEO line',
            'default_og_image' => 'https://example.com/og.png',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    app()->forgetInstance(GeneralSettings::class);
    $reloaded = app(GeneralSettings::class);

    expect($reloaded->site_name)->toBe('Updated Brand')
        ->and($reloaded->default_og_image)->toBe('https://example.com/og.png');
});
