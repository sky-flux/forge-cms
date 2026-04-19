<x-filament-panels::page>
    <div class="space-y-4 max-w-2xl">
        <p class="text-sm text-gray-600 dark:text-gray-400">
            清空各类缓存。生产环境谨慎使用。
            @if ($lastClearedAt)
                上次操作: {{ $lastClearedAt }}
            @endif
        </p>
        <div class="grid grid-cols-2 gap-3">
            <button wire:click="clearConfig" class="rounded border px-4 py-2 hover:bg-gray-50 dark:hover:bg-gray-800">
                清空 Config 缓存
            </button>
            <button wire:click="clearRoute" class="rounded border px-4 py-2 hover:bg-gray-50 dark:hover:bg-gray-800">
                清空 Route 缓存
            </button>
            <button wire:click="clearView" class="rounded border px-4 py-2 hover:bg-gray-50 dark:hover:bg-gray-800">
                清空 View 缓存
            </button>
            <button wire:click="flushApp" wire:confirm="确认清空应用缓存?"
                    class="rounded border border-red-300 px-4 py-2 text-red-700 hover:bg-red-50 dark:hover:bg-red-900/20">
                清空应用缓存 (Cache::flush)
            </button>
        </div>
    </div>
</x-filament-panels::page>
