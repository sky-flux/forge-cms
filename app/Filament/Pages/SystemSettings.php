<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Settings\BackupSettings;
use App\Settings\GeneralSettings;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class SystemSettings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static string|UnitEnum|null $navigationGroup = '系统';

    protected static ?int $navigationSort = 4;

    protected static ?string $navigationLabel = '配置';

    protected static ?string $title = '配置';

    protected static ?string $slug = 'settings';

    protected string $view = 'filament.pages.system-settings';

    /** @var array<string, mixed> */
    public array $data = [];

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);

        $this->data = [
            'general' => app(GeneralSettings::class)->toArray(),
            'backup' => app(BackupSettings::class)->toArray(),
        ];

        $this->form->fill($this->data);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('SystemSettingsTabs')
                    ->tabs([
                        Tab::make('基本信息')
                            ->statePath('general')
                            ->schema([
                                TextInput::make('site_name')->required()->maxLength(128),
                                Textarea::make('site_description')->required()->rows(2)->maxLength(500),
                                TextInput::make('contact_email')->email()->required()->maxLength(255),
                                Textarea::make('default_seo_description')->required()->rows(2)->maxLength(500),
                                TextInput::make('default_og_image')
                                    ->url()
                                    ->maxLength(2048)
                                    ->nullable()
                                    ->helperText('Absolute URL to the default Open Graph image.'),
                            ]),
                        Tab::make('备份')
                            ->statePath('backup')
                            ->schema([
                                Toggle::make('enabled')->label('启用备份'),
                                Select::make('destination_disk')
                                    ->label('存储位置')
                                    ->options(fn () => collect(array_keys(config('filesystems.disks')))
                                        ->mapWithKeys(fn ($k) => [$k => $k])
                                        ->all())
                                    ->required(),
                                Toggle::make('include_storage_files')->label('包含上传文件'),
                                TextInput::make('keep_daily_days')
                                    ->numeric()
                                    ->minValue(1)
                                    ->maxValue(365)
                                    ->label('每日保留天数'),
                                TextInput::make('keep_weekly_weeks')
                                    ->numeric()
                                    ->minValue(0)
                                    ->maxValue(52)
                                    ->label('每周保留数'),
                                TextInput::make('keep_monthly_months')
                                    ->numeric()
                                    ->minValue(0)
                                    ->maxValue(24)
                                    ->label('每月保留月数'),
                                TextInput::make('notify_email')
                                    ->email()
                                    ->nullable()
                                    ->label('失败通知邮箱'),
                                TextInput::make('schedule_hour')
                                    ->numeric()
                                    ->minValue(0)
                                    ->maxValue(23)
                                    ->label('每日执行时刻 (0-23)'),
                            ]),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        abort_unless(static::canAccess(), 403);

        $state = $this->form->getState();

        $general = app(GeneralSettings::class);
        foreach (($state['general'] ?? []) as $key => $value) {
            $general->{$key} = $value;
        }
        $general->save();

        $backup = app(BackupSettings::class);
        foreach (($state['backup'] ?? []) as $key => $value) {
            $backup->{$key} = $this->castBackupValue($key, $value);
        }
        $backup->save();

        Notification::make()
            ->title('Settings saved')
            ->success()
            ->send();
    }

    /**
     * @return array<int, Action>
     */
    protected function getFormActions(): array
    {
        return [
            Action::make('save')->label('Save')->submit('save'),
        ];
    }

    public static function canAccess(): bool
    {
        return auth()->check() && auth()->user()->hasRole('super_admin');
    }

    /**
     * Cast form-submitted backup values to the scalar types declared on BackupSettings.
     *
     * Filament numeric inputs round-trip through HTML and come back as strings
     * or floats, which fails the typed properties on BackupSettings.
     */
    private function castBackupValue(string $key, mixed $value): mixed
    {
        return match ($key) {
            'keep_daily_days', 'keep_weekly_weeks', 'keep_monthly_months', 'schedule_hour' => (int) $value,
            'enabled', 'include_storage_files' => (bool) $value,
            default => $value,
        };
    }
}
