<?php

declare(strict_types=1);

use App\Filament\Pages\Security;
use App\Models\FailedLoginAttempt;
use App\Models\User;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    $this->withoutVite();
    Role::findOrCreate('super_admin');
});

test('security page lives under 系统', function (): void {
    expect(Security::getNavigationGroup())->toBe('系统')
        ->and(Security::getNavigationSort())->toBe(7);
});

test('super_admin can access security page', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');

    $this->actingAs($admin)
        ->get(Security::getUrl())
        ->assertSuccessful();
});

test('guest redirected from security page', function (): void {
    $this->get(Security::getUrl())->assertRedirect('/console/login');
});

test('failed login attempt is recorded', function (): void {
    FailedLoginAttempt::create([
        'email' => 'attacker@evil.com',
        'ip' => '1.2.3.4',
        'user_agent' => 'Mozilla',
        'attempted_at' => now(),
    ]);

    expect(FailedLoginAttempt::count())->toBe(1);
});
