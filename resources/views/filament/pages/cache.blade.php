<x-filament-panels::page>
    @php
        /** @var \App\Filament\Pages\Cache $this */
        $backend = $this->getCacheBackendStats();
        $opcache = $this->getOpcacheStats();
        $recentStats = $this->getRecentActionsStats();
        $recent = $this->getRecentActions();
        $activityUrl = \App\Filament\Resources\ActivityLog\ActivityLogResource::getUrl('index');
    @endphp

    {{-- Stats grid --}}
    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
        {{-- Cache backend --}}
        <x-filament::section>
            <div class="space-y-1">
                <div class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                    缓存后端
                </div>
                <div class="flex items-baseline gap-2">
                    <span class="text-2xl font-semibold text-gray-950 dark:text-white">
                        {{ $backend['driver'] }}
                    </span>
                    @if (($backend['connected'] ?? null) === true)
                        <span class="inline-flex h-2 w-2 rounded-full bg-emerald-500" aria-label="online"></span>
                    @elseif (($backend['connected'] ?? null) === false)
                        <span class="inline-flex h-2 w-2 rounded-full bg-rose-500" aria-label="offline"></span>
                    @endif
                </div>
                @if (($backend['connected'] ?? null) === true)
                    <div class="text-xs text-gray-500 dark:text-gray-400">
                        内存 {{ $backend['memory'] ?? 'n/a' }}
                    </div>
                    <div class="text-xs text-gray-500 dark:text-gray-400">
                        Keys {{ number_format((int) ($backend['keys'] ?? 0)) }}
                    </div>
                    <div class="text-xs text-gray-500 dark:text-gray-400">
                        Uptime {{ \Illuminate\Support\Carbon::now()->subSeconds((int) ($backend['uptime_seconds'] ?? 0))->diffForHumans(null, true) }}
                    </div>
                @elseif (($backend['connected'] ?? null) === false)
                    <div class="text-xs text-rose-600 dark:text-rose-400">
                        连接失败:{{ $backend['error'] ?? 'unknown' }}
                    </div>
                @else
                    <div class="text-xs text-gray-500 dark:text-gray-400">
                        非 Redis 驱动,指标不适用
                    </div>
                @endif
            </div>
        </x-filament::section>

        {{-- Opcache --}}
        <x-filament::section>
            <div class="space-y-1">
                <div class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                    Opcache
                </div>
                <div class="text-2xl font-semibold text-gray-950 dark:text-white">
                    {{ $opcache['enabled'] ? '启用' : '未启用' }}
                </div>
                @if ($opcache['enabled'])
                    @php
                        $used = (int) ($opcache['used_memory'] ?? 0);
                        $free = (int) ($opcache['free_memory'] ?? 0);
                        $totalMb = ($used + $free) > 0 ? round(($used + $free) / 1024 / 1024, 1) : 0;
                        $usedMb = $used > 0 ? round($used / 1024 / 1024, 1) : 0;
                    @endphp
                    <div class="text-xs text-gray-500 dark:text-gray-400">
                        内存 {{ $usedMb }}/{{ $totalMb }} MB
                    </div>
                    <div class="text-xs text-gray-500 dark:text-gray-400">
                        脚本 {{ number_format((int) ($opcache['cached_scripts'] ?? 0)) }}
                    </div>
                    <div class="text-xs text-gray-500 dark:text-gray-400">
                        命中率 {{ number_format((float) ($opcache['hit_rate'] ?? 0), 2) }}%
                    </div>
                @else
                    <div class="text-xs text-gray-500 dark:text-gray-400">
                        opcache 扩展未加载或已禁用
                    </div>
                @endif
            </div>
        </x-filament::section>

        {{-- Total operations --}}
        <x-filament::section>
            <div class="space-y-1">
                <div class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                    清理操作累计
                </div>
                <div class="text-2xl font-semibold text-gray-950 dark:text-white">
                    {{ number_format((int) $recentStats['total']) }} 次
                </div>
                <div class="text-xs text-gray-500 dark:text-gray-400">
                    @if ($recentStats['last_at'])
                        最近 {{ $recentStats['last_at']->diffForHumans() }}
                    @else
                        暂无记录
                    @endif
                </div>
            </div>
        </x-filament::section>

        {{-- Current session --}}
        <x-filament::section>
            <div class="space-y-1">
                <div class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                    本次会话
                </div>
                <div class="text-2xl font-semibold text-gray-950 dark:text-white">
                    @if ($lastClearedAt)
                        刚刚
                    @else
                        —
                    @endif
                </div>
                <div class="text-xs text-gray-500 dark:text-gray-400">
                    @if ($lastClearedAt)
                        {{ $lastClearedAt }}
                    @else
                        本次登录未清理
                    @endif
                </div>
            </div>
        </x-filament::section>
    </div>

    {{-- Warning banner --}}
    <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 dark:border-amber-900/50 dark:bg-amber-950/30">
        <div class="flex items-start gap-3">
            <x-filament::icon
                icon="heroicon-o-exclamation-triangle"
                class="mt-0.5 h-5 w-5 flex-none text-amber-600 dark:text-amber-400"
            />
            <div class="space-y-1 text-sm">
                <p class="font-medium text-amber-900 dark:text-amber-100">
                    清空应用缓存会丢失 Cache::remember 数据(字典 / Sitemap / Feed 等)
                </p>
                <p class="text-amber-800 dark:text-amber-200/90">
                    每次操作均记录到
                    <a
                        href="{{ $activityUrl }}"
                        class="font-medium underline decoration-amber-500 underline-offset-2 hover:decoration-amber-700 dark:decoration-amber-400 dark:hover:decoration-amber-200"
                    >活动</a>
                    页面,可按 log_name=cache 筛选审计。
                </p>
            </div>
        </div>
    </div>

    {{-- Recent actions table --}}
    <x-filament::section>
        <x-slot name="heading">最近 10 次清理</x-slot>

        @if (count($recent) === 0)
            <p class="text-sm text-gray-500 dark:text-gray-400">
                暂无记录。首次点击上方按钮后将在此展示。
            </p>
        @else
            <div class="overflow-hidden rounded-md border border-gray-200 dark:border-white/10">
                <table class="w-full divide-y divide-gray-200 text-sm dark:divide-white/10">
                    <thead class="bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500 dark:bg-white/5 dark:text-gray-400">
                        <tr>
                            <th scope="col" class="px-3 py-2 font-medium">时间</th>
                            <th scope="col" class="px-3 py-2 font-medium">操作</th>
                            <th scope="col" class="px-3 py-2 font-medium">描述</th>
                            <th scope="col" class="px-3 py-2 font-medium">操作人</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                        @foreach ($recent as $row)
                            <tr class="text-gray-900 dark:text-gray-100">
                                <td class="px-3 py-2 text-gray-500 dark:text-gray-400">
                                    {{ $row['at']?->diffForHumans() ?? '—' }}
                                </td>
                                <td class="px-3 py-2 font-mono text-xs">
                                    {{ $row['event'] ?? '—' }}
                                </td>
                                <td class="px-3 py-2">
                                    {{ $row['description'] }}
                                </td>
                                <td class="px-3 py-2 text-gray-500 dark:text-gray-400">
                                    {{ $row['causer'] ?? '系统' }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-filament::section>
</x-filament-panels::page>
