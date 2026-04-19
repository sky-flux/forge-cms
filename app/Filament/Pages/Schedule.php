<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Models\ScheduledTaskRun;
use BackedEnum;
use Carbon\CarbonImmutable;
use Cron\CronExpression;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule as ConsoleSchedule;
use UnitEnum;

class Schedule extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    protected static string|UnitEnum|null $navigationGroup = '系统';

    protected static ?int $navigationSort = 5;

    protected static ?string $navigationLabel = '调度';

    protected static ?string $title = '调度';

    protected static ?string $slug = 'schedule';

    protected string $view = 'filament.pages.schedule';

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);
    }

    public static function canAccess(): bool
    {
        return auth()->check() && auth()->user()->hasRole('super_admin');
    }

    /**
     * Build a row per registered scheduled event for the read-only table.
     *
     * @return array<int, array{
     *     command: string,
     *     description: string,
     *     expression: string,
     *     last_run_at: ?CarbonImmutable,
     *     last_exit_code: ?int,
     *     next_run_at: ?CarbonImmutable,
     * }>
     */
    public function getTableData(): array
    {
        return collect(app(ConsoleSchedule::class)->events())
            ->map(function (Event $event): array {
                $command = $this->commandKey($event);
                $lastRun = ScheduledTaskRun::query()
                    ->where('command', $command)
                    ->latest('started_at')
                    ->first();

                return [
                    'command' => $command,
                    'description' => (string) ($event->description ?? ''),
                    'expression' => (string) $event->expression,
                    'last_run_at' => $lastRun?->started_at,
                    'last_exit_code' => $lastRun?->exit_code,
                    'next_run_at' => $this->computeNextRun((string) $event->expression),
                ];
            })
            ->all();
    }

    protected function commandKey(Event $event): string
    {
        if (! empty($event->command)) {
            return (string) $event->command;
        }

        if (! empty($event->description)) {
            return (string) $event->description;
        }

        return 'closure';
    }

    protected function computeNextRun(string $expression): ?CarbonImmutable
    {
        try {
            $next = (new CronExpression($expression))->getNextRunDate(now()->toDateTime());

            return CarbonImmutable::instance($next);
        } catch (\Throwable) {
            return null;
        }
    }
}
