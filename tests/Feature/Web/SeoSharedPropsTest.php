<?php

declare(strict_types=1);

use App\Settings\SeoSettings;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->withoutVite();
});

test('public pages expose SEO shared props from settings', function (): void {
    $s = app(SeoSettings::class);
    $s->google_analytics_id = 'G-ABC123';
    $s->twitter_site_handle = '@forge';
    $s->save();

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('seo.google_analytics_id', 'G-ABC123')
            ->where('seo.twitter_site_handle', '@forge')
        );
});
