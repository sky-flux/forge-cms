<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Settings\GeneralSettings;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
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

    protected static ?string $slug = 'system-settings';

    protected string $view = 'filament.pages.system-settings';

    /** @var array<string, mixed> */
    public array $data = [];

    public function mount(): void
    {
        $this->form->fill(app(GeneralSettings::class)->toArray());
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('site_name')->required()->maxLength(128),
                Textarea::make('site_description')->required()->rows(2)->maxLength(500),
                TextInput::make('contact_email')->email()->required()->maxLength(255),
                Textarea::make('default_seo_description')->required()->rows(2)->maxLength(500),
                TextInput::make('default_og_image')
                    ->url()
                    ->maxLength(2048)
                    ->nullable()
                    ->helperText('Absolute URL to the default Open Graph image.'),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $state = $this->form->getState();

        $settings = app(GeneralSettings::class);
        foreach ($state as $key => $value) {
            $settings->{$key} = $value;
        }
        $settings->save();

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
}
