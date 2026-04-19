<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\ScheduledTaskRun;
use App\Policies\ActivityLogPolicy;
use App\Policies\MediaPolicy;
use Carbon\CarbonImmutable;
use Illuminate\Console\Events\ScheduledTaskFinished;
use Illuminate\Console\Events\ScheduledTaskStarting;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Spatie\Activitylog\Models\Activity;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        JsonResource::withoutWrapping();

        Model::preventLazyLoading(! $this->app->isProduction());

        Gate::before(function ($user) {
            return $user?->hasRole('super_admin') ? true : null;
        });

        // Spatie Media lives outside App\Models so Laravel's auto-discovery
        // can't map it to MediaPolicy — register the mapping explicitly.
        Gate::policy(
            Media::class,
            MediaPolicy::class,
        );

        // Spatie Activity lives outside App\Models so Laravel's auto-discovery
        // can't map it to ActivityLogPolicy — register the mapping explicitly.
        Gate::policy(
            Activity::class,
            ActivityLogPolicy::class,
        );

        $this->configureDefaults();
        $this->registerScheduleListeners();
    }

    /**
     * Persist a history row for every scheduled task run so the admin
     * `系统 → 调度` page can display last-run metadata.
     */
    protected function registerScheduleListeners(): void
    {
        // Map spl_object_id($task) => scheduled_task_runs.id so the
        // Finished listener can update the exact row created on Starting,
        // even when several tasks run in the same process.
        $runIdByTask = [];

        Event::listen(function (ScheduledTaskStarting $event) use (&$runIdByTask): void {
            $run = ScheduledTaskRun::create([
                'command' => $this->commandKeyForEvent($event->task),
                'started_at' => now(),
            ]);

            $runIdByTask[spl_object_id($event->task)] = $run->id;
        });

        Event::listen(function (ScheduledTaskFinished $event) use (&$runIdByTask): void {
            $key = spl_object_id($event->task);
            $id = $runIdByTask[$key] ?? null;
            unset($runIdByTask[$key]);

            $run = $id ? ScheduledTaskRun::find($id) : null;

            $run ??= ScheduledTaskRun::where('command', $this->commandKeyForEvent($event->task))
                ->whereNull('finished_at')
                ->latest('started_at')
                ->first();

            if ($run === null) {
                return;
            }

            $output = null;
            $outputPath = $event->task->output ?? null;
            if (is_string($outputPath) && is_file($outputPath)) {
                $output = Str::limit((string) file_get_contents($outputPath), 500, '');
            }

            $run->update([
                'finished_at' => now(),
                'exit_code' => $event->task->exitCode ?? 0,
                'output' => $output,
            ]);
        });
    }

    /**
     * Normalize a schedule Event into the identifier we store in
     * `scheduled_task_runs.command`.
     */
    protected function commandKeyForEvent(object $task): string
    {
        if (! empty($task->command)) {
            return (string) $task->command;
        }

        if (! empty($task->description)) {
            return (string) $task->description;
        }

        return 'closure';
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
