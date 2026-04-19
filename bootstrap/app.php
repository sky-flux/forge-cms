<?php

use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Settings\BackupSettings;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Inertia\Inertia;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if ($request->header('X-Inertia') || $request->acceptsHtml()) {
                return Inertia::render('Errors/NotFound')
                    ->toResponse($request)
                    ->setStatusCode(404);
            }

            return null;
        });
    })
    ->withSchedule(function (Schedule $schedule): void {
        $schedule->command('posts:publish-scheduled')
            ->everyMinute()
            ->withoutOverlapping()
            ->onOneServer();

        $schedule->call(function (): void {
            $settings = app(BackupSettings::class);
            if (! $settings->enabled) {
                return;
            }

            config([
                'backup.destination.disks' => [$settings->destination_disk],
                'backup.cleanup.default_strategy.keep_daily_backups_for_days' => $settings->keep_daily_days,
                'backup.cleanup.default_strategy.keep_weekly_backups_for_weeks' => $settings->keep_weekly_weeks,
                'backup.cleanup.default_strategy.keep_monthly_backups_for_months' => $settings->keep_monthly_months,
                'backup.source.files.include' => $settings->include_storage_files ? [storage_path()] : [],
                'backup.notifications.mail.to' => $settings->notify_email,
            ]);

            Artisan::call('backup:clean');
            Artisan::call(
                'backup:run',
                $settings->include_storage_files ? [] : ['--only-db' => true],
            );
        })->description('forge.backup')
            ->dailyAt(sprintf('%02d:00', app(BackupSettings::class)->schedule_hour))
            ->onOneServer();
    })->create();
