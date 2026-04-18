<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Fortify\Features;
use Laravel\Telescope\Telescope;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Keep Telescope's provider registered (so /telescope routes and gate
        // assertions still work) but stop it from queuing entries. Without
        // this, `ListensForStorageOpportunities::storeEntriesBeforeTermination`
        // fires on artisan test termination and tries to flush to the default
        // (pgsql) connection, which is unreachable from the host.
        if (class_exists(Telescope::class)) {
            Telescope::stopRecording();
        }
    }

    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }
}
