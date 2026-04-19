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
            <x-slot name="heading">
                缓存后端
            </x-slot>
            <x-slot name="description">
                @if (($backend['connected'] ?? null) === true)
                    <span class="text-success-500">● 已连接</span>
                @elseif (($backend['connected'] ?? null) === false)
                    <span class="text-danger-500">● 离线</span>
                @endif
            </x-slot>

            <div class="space-y-1">
                <p class="text-2xl font-semibold text-gray-950 dark:text-white">{{ $backend['driver'] }}</p>
                @if (($backend['connected'] ?? null) === true)
                    <p class="text-xs text-gray-500 dark:text-gray-400">内存 {{ $backend['memory'] ?? 'n/a' }}</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Keys {{ number_format((int) ($backend['keys'] ?? 0)) }}</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Uptime {{ \Illuminate\Support\Carbon::now()->subSeconds((int) ($backend['uptime_seconds'] ?? 0))->diffForHumans(null, true) }}</p>
                @elseif (($backend['connected'] ?? null) === false)
                    <p class="text-xs text-danger-500">连接失败:{{ $backend['error'] ?? 'unknown' }}</p>
                @else
                    <p class="text-xs text-gray-500 dark:text-gray-400">非 Redis 驱动,指标不适用</p>
                @endif
            </div>
        </x-filament::section>

        {{-- Opcache --}}
        <x-filament::section>
            <x-slot name="heading">
                Opcache
            </x-slot>

            <div class="space-y-1">
                <p class="text-2xl font-semibold text-gray-950 dark:text-white">{{ $opcache['enabled'] ? '启用' : '未启用' }}</p>
                @if ($opcache['enabled'])
                    @php
                        $used = (int) ($opcache['used_memory'] ?? 0);
                        $free = (int) ($opcache['free_memory'] ?? 0);
                        $totalMb = ($used + $free) > 0 ? round(($used + $free) / 1024 / 1024, 1) : 0;
                        $usedMb = $used > 0 ? round($used / 1024 / 1024, 1) : 0;
                    @endphp
                    <p class="text-xs text-gray-500 dark:text-gray-400">内存 {{ $usedMb }}/{{ $totalMb }} MB</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">脚本 {{ number_format((int) ($opcache['cached_scripts'] ?? 0)) }}</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">命中率 {{ number_format((float) ($opcache['hit_rate'] ?? 0), 2) }}%</p>
                @else
                    <p class="text-xs text-gray-500 dark:text-gray-400">opcache 扩展未加载或已禁用</p>
                @endif
            </div>
        </x-filament::section>

        {{-- Total operations --}}
        <x-filament::section>
            <x-slot name="heading">
                清理操作累计
            </x-slot>

            <div class="space-y-1">
                <p class="text-2xl font-semibold text-gray-950 dark:text-white">{{ number_format((int) $recentStats['total']) }} 次</p>
                <p class="text-xs text-gray-500 dark:text-gray-400">
                    @if ($recentStats['last_at'])
                        最近 {{ $recentStats['last_at']->diffForHumans() }}
                    @else
                        暂无记录
                    @endif
                </p>
            </div>
        </x-filament::section>

        {{-- Current session --}}
        <x-filament::section>
            <x-slot name="heading">
                本次会话
            </x-slot>

            <div class="space-y-1">
                <p class="text-2xl font-semibold text-gray-950 dark:text-white">
                    @if ($lastClearedAt)
                        刚刚
                    @else
                        —
                    @endif
                </p>
                <p class="text-xs text-gray-500 dark:text-gray-400">
                    @if ($lastClearedAt)
                        {{ $lastClearedAt }}
                    @else
                        本次登录未清理
                    @endif
                </p>
            </div>
        </x-filament::section>
    </div>

    {{-- Warning banner --}}
    <x-filament::section aside icon="heroicon-o-exclamation-triangle" icon-color="warning">
        <x-slot name="heading">
            注意
        </x-slot>
        <x-slot name="description">
            清空应用缓存会丢失 Cache::remember 数据(字典 / Sitemap / Feed 等)。
            每次操作均记录到
            <a href="{{ $activityUrl }}" class="underline">活动</a>
            页面,可按 log_name=cache 筛选审计。
        </x-slot>
    </x-filament::section>

    {{-- Recent actions table --}}
    <x-filament::section>
        <x-slot name="heading">最近 10 次清理</x-slot>

        @if (count($recent) === 0)
            <p class="text-sm text-gray-500 dark:text-gray-400">暂无记录。首次点击上方按钮后将在此展示。</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 dark:bg-gray-800">
                        <tr class="border-b border-gray-200 dark:border-white/10">
                            <th class="text-left p-2 font-medium">时间</th>
                            <th class="text-left p-2 font-medium">操作</th>
                            <th class="text-left p-2 font-medium">描述</th>
                            <th class="text-left p-2 font-medium">操作人</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($recent as $row)
                            <tr class="border-b border-gray-100 dark:border-white/5">
                                <td class="p-2 text-xs text-gray-500 dark:text-gray-400">
                                    {{ $row['at']?->diffForHumans() ?? '—' }}
                                </td>
                                <td class="p-2">
                                    <code class="text-xs">{{ $row['event'] ?? '—' }}</code>
                                </td>
                                <td class="p-2">
                                    {{ $row['description'] }}
                                </td>
                                <td class="p-2 text-xs text-gray-500 dark:text-gray-400">
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
