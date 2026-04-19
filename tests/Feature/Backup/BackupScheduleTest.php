<?php

declare(strict_types=1);

use App\Settings\BackupSettings;
use Illuminate\Console\Scheduling\CallbackEvent;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;

/**
 * Locate the forge.backup scheduled callback event by its description marker.
 *
 * Ensures the Artisan console application is booted first so that
 * `withSchedule(...)` callbacks (registered via `Artisan::starting`) fire
 * against the current test application instance.
 */
function forgeBackupScheduledEvent(): ?CallbackEvent
{
    // Boot the Artisan console application so that callbacks registered via
    // `Artisan::starting(...)` (which is what `withSchedule(...)` relies on)
    // fire and wire `afterResolving(Schedule::class, ...)` against the
    // current test application before we resolve the Schedule singleton.
    $kernel = app(Kernel::class);
    $kernel->bootstrap();
    $ref = new ReflectionMethod($kernel, 'getArtisan');
    $ref->setAccessible(true);
    $ref->invoke($kernel);

    $schedule = app(Schedule::class);

    /** @var CallbackEvent|null $event */
    $event = collect($schedule->events())->first(
        fn ($e) => $e instanceof CallbackEvent
            && str_contains((string) $e->description, 'forge.backup'),
    );

    return $event;
}

/**
 * Invoke the scheduled callback directly via reflection (callback prop is protected).
 */
function invokeScheduledCallback(CallbackEvent $event): void
{
    $ref = new ReflectionClass($event);
    $prop = $ref->getProperty('callback');
    $prop->setAccessible(true);
    $callback = $prop->getValue($event);
    $callback();
}

test('backup schedule invokes backup:clean and backup:run when enabled', function (): void {
    $settings = app(BackupSettings::class);
    $settings->enabled = true;
    $settings->destination_disk = 'local';
    $settings->include_storage_files = false;
    $settings->keep_daily_days = 7;
    $settings->save();

    // Pre-resolve Schedule before swapping the Artisan facade to avoid the
    // console kernel mock conflicting with Schedule resolution.
    $event = forgeBackupScheduledEvent();
    expect($event)->not->toBeNull();

    Artisan::shouldReceive('call')->with('backup:clean')->once();
    Artisan::shouldReceive('call')->with('backup:run', ['--only-db' => true])->once();

    invokeScheduledCallback($event);
});

test('backup schedule no-ops when disabled', function (): void {
    $settings = app(BackupSettings::class);
    $settings->enabled = false;
    $settings->save();

    $event = forgeBackupScheduledEvent();
    expect($event)->not->toBeNull();

    Artisan::shouldReceive('call')->never();

    invokeScheduledCallback($event);
});
