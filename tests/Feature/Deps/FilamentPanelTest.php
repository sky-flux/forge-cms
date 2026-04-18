<?php

declare(strict_types=1);

use App\Models\User;

test('redirects guests from /admin to the filament login page', function (): void {
    $response = $this->get('/admin');

    $response->assertRedirect('/admin/login');
});

test('renders the filament dashboard for an authenticated user allowed into the panel', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/admin')->assertSuccessful();
});
