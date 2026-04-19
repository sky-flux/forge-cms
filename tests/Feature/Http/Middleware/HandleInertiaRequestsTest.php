<?php

declare(strict_types=1);

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('auth.user only exposes whitelisted fields and omits 2FA secrets', function () {
    $user = User::factory()->create();

    $user->forceFill([
        'two_factor_secret' => encrypt('test-secret'),
        'two_factor_recovery_codes' => encrypt(json_encode(['code1', 'code2'])),
        'two_factor_confirmed_at' => now(),
    ])->save();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.user.id', $user->id)
            ->where('auth.user.name', $user->name)
            ->where('auth.user.email', $user->email)
            ->where('auth.user.two_factor_enabled', true)
            ->has('auth.user.avatar')
            ->missing('auth.user.two_factor_secret')
            ->missing('auth.user.two_factor_recovery_codes')
            ->missing('auth.user.two_factor_confirmed_at')
            ->missing('auth.user.remember_token')
            ->missing('auth.user.password'),
        );
});

test('auth.user is null for guests', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.user', null),
        );
});

test('two_factor_enabled is false when user has no 2FA secret', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.user.two_factor_enabled', false)
            ->missing('auth.user.two_factor_secret'),
        );
});
