<x-filament-panels::page>
    <div class="space-y-8">
        <section>
            <h2 class="text-lg font-semibold mb-3">活动会话 ({{ count($this->getSessionsData()) }})</h2>
            <table class="w-full text-sm border">
                <thead class="bg-gray-50 dark:bg-gray-800">
                    <tr class="border-b">
                        <th class="text-left p-2">用户</th>
                        <th class="text-left p-2">IP</th>
                        <th class="text-left p-2">User-Agent</th>
                        <th class="text-left p-2">最近活动</th>
                        <th class="text-left p-2"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->getSessionsData() as $s)
                        <tr class="border-b">
                            <td class="p-2">{{ $s['user_name'] ?? '匿名' }}</td>
                            <td class="p-2 font-mono text-xs">{{ $s['ip'] }}</td>
                            <td class="p-2 text-xs truncate max-w-md">{{ $s['user_agent'] }}</td>
                            <td class="p-2 text-xs">{{ $s['last_activity']?->diffForHumans() }}</td>
                            <td class="p-2">
                                <button
                                    type="button"
                                    wire:click="forceLogout('{{ $s['id'] }}')"
                                    wire:confirm="确认强制注销这个会话?"
                                    class="text-red-600 text-xs hover:underline">强制注销</button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="p-4 text-center text-gray-400">(无活动会话)</td></tr>
                    @endforelse
                </tbody>
            </table>
        </section>

        <section>
            <h2 class="text-lg font-semibold mb-3">登录失败尝试 (最近 50)</h2>
            <table class="w-full text-sm border">
                <thead class="bg-gray-50 dark:bg-gray-800">
                    <tr class="border-b">
                        <th class="text-left p-2">Email</th>
                        <th class="text-left p-2">IP</th>
                        <th class="text-left p-2">时间</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->getFailedLoginsData() as $f)
                        <tr class="border-b">
                            <td class="p-2">{{ $f['email'] }}</td>
                            <td class="p-2 font-mono text-xs">{{ $f['ip'] }}</td>
                            <td class="p-2 text-xs">{{ $f['attempted_at']?->diffForHumans() }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="p-4 text-center text-gray-400">(无记录)</td></tr>
                    @endforelse
                </tbody>
            </table>
        </section>

        <section>
            <h2 class="text-lg font-semibold mb-3">API Tokens</h2>
            <table class="w-full text-sm border">
                <thead class="bg-gray-50 dark:bg-gray-800">
                    <tr class="border-b">
                        <th class="text-left p-2">用户</th>
                        <th class="text-left p-2">Token 名称</th>
                        <th class="text-left p-2">能力</th>
                        <th class="text-left p-2">最近使用</th>
                        <th class="text-left p-2"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->getTokensData() as $t)
                        <tr class="border-b">
                            <td class="p-2">{{ $t['user_name'] }}</td>
                            <td class="p-2">{{ $t['name'] }}</td>
                            <td class="p-2 text-xs">{{ implode(', ', $t['abilities']) ?: '*' }}</td>
                            <td class="p-2 text-xs">{{ $t['last_used_at']?->diffForHumans() ?? '从未使用' }}</td>
                            <td class="p-2">
                                <button
                                    type="button"
                                    wire:click="revokeToken({{ $t['id'] }})"
                                    wire:confirm="确认撤销这个 token?"
                                    class="text-red-600 text-xs hover:underline">撤销</button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="p-4 text-center text-gray-400">(无 token)</td></tr>
                    @endforelse
                </tbody>
            </table>
        </section>
    </div>
</x-filament-panels::page>
