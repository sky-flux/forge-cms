<?php

declare(strict_types=1);

test('returns a successful response', function (): void {
    // Home.tsx is not yet in the Vite manifest in CI/local until `bun run build` runs,
    // so short-circuit manifest lookups (same pattern as tests/Feature/Web/*.php).
    $this->withoutVite();

    $response = $this->get(route('home'));

    $response->assertOk();
});
