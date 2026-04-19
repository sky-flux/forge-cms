<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache as CacheFacade;
use UnitEnum;

class Cache extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBolt;

    protected static string|UnitEnum|null $navigationGroup = '系统';

    protected static ?int $navigationSort = 9;

    protected static ?string $navigationLabel = '缓存管理';

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

    public function clearConfig(): void
    {
        abort_unless(static::canAccess(), 403);

        Artisan::call('config:clear');
        $this->lastClearedAt = now()->toDateTimeString();

        Notification::make()->title('Config 缓存已清')->success()->send();
    }

    public function clearRoute(): void
    {
        abort_unless(static::canAccess(), 403);

        Artisan::call('route:clear');
        $this->lastClearedAt = now()->toDateTimeString();

        Notification::make()->title('Route 缓存已清')->success()->send();
    }

    public function clearView(): void
    {
        abort_unless(static::canAccess(), 403);

        Artisan::call('view:clear');
        $this->lastClearedAt = now()->toDateTimeString();

        Notification::make()->title('View 缓存已清')->success()->send();
    }

    public function flushApp(): void
    {
        abort_unless(static::canAccess(), 403);

        CacheFacade::flush();
        $this->lastClearedAt = now()->toDateTimeString();

        Notification::make()->title('应用缓存已清空')->success()->send();
    }
}
