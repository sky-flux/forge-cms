<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Mail;
use UnitEnum;

class MailTest extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedEnvelope;

    protected static string|UnitEnum|null $navigationGroup = '系统';

    protected static ?int $navigationSort = 8;

    protected static ?string $navigationLabel = '邮件';

    protected static ?string $title = '邮件';

    protected static ?string $slug = 'mail-test';

    protected string $view = 'filament.pages.mail-test';

    public string $recipient = '';

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);

        $this->recipient = auth()->user()?->email ?? '';
    }

    public static function canAccess(): bool
    {
        return auth()->check() && auth()->user()->hasRole('super_admin');
    }

    public function send(): void
    {
        abort_unless(static::canAccess(), 403);

        $this->validate(['recipient' => 'required|email']);

        try {
            Mail::raw('This is a test email from ForgeCMS admin panel.', function ($message): void {
                $message->to($this->recipient)->subject('ForgeCMS Test Email');
            });

            Notification::make()
                ->title('测试邮件已发送')
                ->body("收件人: {$this->recipient}")
                ->success()
                ->send();
        } catch (\Throwable $e) {
            Notification::make()
                ->title('发送失败')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }
}
