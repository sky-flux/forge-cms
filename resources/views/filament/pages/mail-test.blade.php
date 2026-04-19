<x-filament-panels::page>
    <form wire:submit="send" class="space-y-4 max-w-md">
        <div>
            <label for="recipient" class="block text-sm font-medium">收件人</label>
            <input type="email" id="recipient" wire:model="recipient" required
                   class="mt-1 w-full rounded border px-3 py-2" />
        </div>
        <button type="submit" class="rounded bg-primary px-4 py-2 text-primary-foreground disabled:opacity-50"
                wire:loading.attr="disabled">
            <span wire:loading.remove>发送测试邮件</span>
            <span wire:loading>发送中…</span>
        </button>
    </form>
</x-filament-panels::page>
