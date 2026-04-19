<?php

declare(strict_types=1);

beforeEach(function (): void {
    $this->withoutVite();
});

test('unknown URL returns 404 with the Errors/NotFound Inertia component', function (): void {
    $this->get('/this-does-not-exist-anywhere')
        ->assertNotFound()
        ->assertInertia(fn ($inertia) => $inertia->component('Errors/NotFound', false));
});

test('404 Inertia response preserves status 404', function (): void {
    $response = $this->get('/another-unknown-route');

    expect($response->status())->toBe(404);
});
