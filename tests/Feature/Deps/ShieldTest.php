<?php

declare(strict_types=1);

use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
use BezhanSalleh\FilamentShield\Support\Utils;
use Filament\Facades\Filament;

test('registers the super_admin role name configured by shield', function (): void {
    expect(Utils::getSuperAdminName())->toBe('super_admin');
});

test('registers the filament shield plugin on the admin panel', function (): void {
    $panel = Filament::getPanel('admin');

    expect($panel->getPlugin('filament-shield'))
        ->toBeInstanceOf(FilamentShieldPlugin::class);
});
