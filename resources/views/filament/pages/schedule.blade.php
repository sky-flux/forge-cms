<x-filament-panels::page>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b">
                    <th class="text-left p-2">命令</th>
                    <th class="text-left p-2">描述</th>
                    <th class="text-left p-2">Cron</th>
                    <th class="text-left p-2">上次运行</th>
                    <th class="text-left p-2">状态</th>
                    <th class="text-left p-2">下次运行</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($this->getTableData() as $row)
                    <tr class="border-b">
                        <td class="p-2 font-mono text-xs">{{ $row['command'] }}</td>
                        <td class="p-2">{{ $row['description'] }}</td>
                        <td class="p-2 font-mono text-xs">{{ $row['expression'] }}</td>
                        <td class="p-2 text-xs">{{ $row['last_run_at']?->diffForHumans() ?? '—' }}</td>
                        <td class="p-2">
                            @if ($row['last_exit_code'] === 0)
                                <span class="text-green-600">✓ 成功</span>
                            @elseif ($row['last_exit_code'] !== null)
                                <span class="text-red-600">✗ 失败 ({{ $row['last_exit_code'] }})</span>
                            @else
                                <span class="text-gray-400">—</span>
                            @endif
                        </td>
                        <td class="p-2 text-xs">{{ $row['next_run_at']?->diffForHumans() ?? '—' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td class="p-4 text-center text-gray-500" colspan="6">没有注册的计划任务</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-filament-panels::page>
