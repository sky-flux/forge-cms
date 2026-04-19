<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use BackedEnum;
use Carbon\CarbonInterface;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache as CacheFacade;
use Illuminate\Support\Facades\Redis;
use Spatie\Activitylog\Models\Activity;
use Throwable;
use UnitEnum;

class Cache extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBolt;

    protected static string|UnitEnum|null $navigationGroup = '系统';

    protected static ?int $navigationSort = 9;

    protected static ?string $navigationLabel = '缓存';

    protected static ?string $title = '缓存';

    protected static ?string $slug = 'cache';

    protected string $view = 'filament.pages.cache';

    public ?string $lastClearedAt = null;

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);
    }

    public static function canAccess(): bool
    {
        return auth()->check() && auth()->user()->hasRole('super_admin');
    }

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('clearConfig')
                ->label('清空 Config')
                ->icon(Heroicon::OutlinedCog6Tooth)
                ->color('gray')
                ->action(fn () => $this->clearConfig()),
            Action::make('clearRoute')
                ->label('清空 Route')
                ->icon(Heroicon::OutlinedMap)
                ->color('gray')
                ->action(fn () => $this->clearRoute()),
            Action::make('clearView')
                ->label('清空 View')
                ->icon(Heroicon::OutlinedEye)
                ->color('gray')
                ->action(fn () => $this->clearView()),
            Action::make('clearEvent')
                ->label('清空 Event')
                ->icon(Heroicon::OutlinedBolt)
                ->color('gray')
                ->action(fn () => $this->clearEvent()),
            Action::make('resetOpcache')
                ->label('重置 Opcache')
                ->icon(Heroicon::OutlinedCpuChip)
                ->color('warning')
                ->visible(fn (): bool => function_exists('opcache_reset'))
                ->requiresConfirmation()
                ->modalHeading('确认重置 Opcache?')
                ->modalDescription('当前 worker 的字节码将失效,下一次请求会触发全部脚本重编译,高 QPS 下可能延迟尖峰。')
                ->modalSubmitActionLabel('确认重置')
                ->action(fn () => $this->resetOpcache()),
            Action::make('flushApp')
                ->label('清空应用缓存')
                ->icon(Heroicon::OutlinedTrash)
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('确认清空应用缓存?')
                ->modalDescription('Cache::flush() 会清空所有 Cache::remember 数据(如字典、Sitemap、Feed 缓存),可能在短时间内增加数据库/外部服务压力。操作将记录到活动日志。')
                ->modalSubmitActionLabel('确认清空')
                ->action(fn () => $this->flushApp()),
        ];
    }

    public function clearConfig(): void
    {
        abort_unless(static::canAccess(), 403);

        Artisan::call('config:clear');
        $this->recordAction('config:clear', 'Config 缓存已清');
    }

    public function clearRoute(): void
    {
        abort_unless(static::canAccess(), 403);

        Artisan::call('route:clear');
        $this->recordAction('route:clear', 'Route 缓存已清');
    }

    public function clearView(): void
    {
        abort_unless(static::canAccess(), 403);

        Artisan::call('view:clear');
        $this->recordAction('view:clear', 'View 缓存已清');
    }

    public function clearEvent(): void
    {
        abort_unless(static::canAccess(), 403);

        Artisan::call('event:clear');
        $this->recordAction('event:clear', 'Event 缓存已清');
    }

    public function flushApp(): void
    {
        abort_unless(static::canAccess(), 403);

        CacheFacade::flush();
        $this->recordAction('cache:flush', '应用缓存已清空');
    }

    public function resetOpcache(): void
    {
        abort_unless(static::canAccess(), 403);

        if (! function_exists('opcache_reset')) {
            Notification::make()
                ->title('Opcache 未启用')
                ->warning()
                ->send();

            return;
        }

        opcache_reset();
        $this->recordAction('opcache:reset', 'Opcache 已重置');
    }

    /**
     * Record the action in the activity log, update UI state, and notify.
     */
    private function recordAction(string $event, string $title): void
    {
        $this->lastClearedAt = now()->toDateTimeString();

        activity('cache')
            ->causedBy(auth()->user())
            ->event($event)
            ->log($title);

        Notification::make()->title($title)->success()->send();
    }

    /**
     * Stats about the cache backend (driver, connection status, memory, keys).
     *
     * @return array{
     *     driver: string,
     *     connected?: bool,
     *     memory?: string,
     *     keys?: int,
     *     uptime_seconds?: int,
     *     error?: string,
     * }
     */
    public function getCacheBackendStats(): array
    {
        $driver = (string) config('cache.default');

        if ($driver !== 'redis') {
            return ['driver' => $driver];
        }

        try {
            $info = Redis::connection('cache')->info();

            // phpredis returns root-level 'used_memory_human'; predis nests it under 'Memory'.
            $memory = $info['used_memory_human']
                ?? $info['Memory']['used_memory_human']
                ?? 'n/a';
            $uptime = (int) (
                $info['uptime_in_seconds']
                ?? $info['Server']['uptime_in_seconds']
                ?? 0
            );

            // phpredis: 'db0' => "keys=N,expires=X,avg_ttl=Y" (root-level string)
            // predis:   'Keyspace' => ['db0' => ['keys' => N, ...]] (nested array)
            $keys = 0;
            foreach ($info as $k => $v) {
                if (is_array($v) && isset($v['keys'])) {
                    $keys += (int) $v['keys'];
                } elseif (is_string($v) && str_starts_with((string) $k, 'db')) {
                    $parsed = [];
                    parse_str(str_replace(',', '&', $v), $parsed);
                    $keys += (int) ($parsed['keys'] ?? 0);
                } elseif ($k === 'Keyspace' && is_array($v)) {
                    foreach ($v as $db) {
                        if (is_array($db)) {
                            $keys += (int) ($db['keys'] ?? 0);
                        }
                    }
                }
            }

            return [
                'driver' => 'redis',
                'connected' => true,
                'memory' => (string) $memory,
                'keys' => $keys,
                'uptime_seconds' => $uptime,
            ];
        } catch (Throwable $e) {
            return [
                'driver' => 'redis',
                'connected' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Probe Opcache. Extracted so tests can override via anonymous subclass
     * to exercise both enabled and disabled branches regardless of local PHP config.
     *
     * @return array<string, mixed>|null
     */
    protected function opcacheStatus(): ?array
    {
        if (! function_exists('opcache_get_status')) {
            return null;
        }

        $status = @opcache_get_status(false);

        return is_array($status) ? $status : null;
    }

    /**
     * @return array{
     *     enabled: bool,
     *     used_memory?: int,
     *     free_memory?: int,
     *     cached_scripts?: int,
     *     hit_rate?: float,
     * }
     */
    public function getOpcacheStats(): array
    {
        $status = $this->opcacheStatus();

        if ($status === null) {
            return ['enabled' => false];
        }

        $memory = $status['memory_usage'] ?? [];
        $statistics = $status['opcache_statistics'] ?? [];

        return [
            'enabled' => true,
            'used_memory' => (int) ($memory['used_memory'] ?? 0),
            'free_memory' => (int) ($memory['free_memory'] ?? 0),
            'cached_scripts' => (int) ($statistics['num_cached_scripts'] ?? 0),
            'hit_rate' => (float) ($statistics['opcache_hit_rate'] ?? 0.0),
        ];
    }

    /**
     * Most recent cache-management actions, newest first.
     *
     * @return array<int, array{
     *     id: int,
     *     event: ?string,
     *     description: string,
     *     causer: ?string,
     *     at: ?CarbonInterface,
     * }>
     */
    public function getRecentActions(): array
    {
        return Activity::query()
            ->where('log_name', 'cache')
            ->with('causer')
            ->latest('id')
            ->limit(10)
            ->get()
            ->map(fn (Activity $a): array => [
                'id' => (int) $a->id,
                'event' => $a->event,
                'description' => (string) $a->description,
                'causer' => $a->causer?->email ?? $a->causer?->name ?? null,
                'at' => $a->created_at,
            ])
            ->all();
    }

    /**
     * Aggregate counters over the `cache` activity log.
     *
     * @return array{total: int, last_at: ?CarbonInterface}
     */
    public function getRecentActionsStats(): array
    {
        $query = Activity::query()->where('log_name', 'cache');

        $last = (clone $query)->latest('id')->first();

        return [
            'total' => (int) $query->count(),
            'last_at' => $last?->created_at,
        ];
    }
}
