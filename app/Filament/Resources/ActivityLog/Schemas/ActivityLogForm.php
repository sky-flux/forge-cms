<?php

declare(strict_types=1);

namespace App\Filament\Resources\ActivityLog\Schemas;

use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class ActivityLogForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('log_name')->disabled(),
            Textarea::make('description')->disabled()->rows(2),
            TextInput::make('subject_type')->disabled()->label('Subject'),
            TextInput::make('subject_id')->disabled(),
            TextInput::make('causer_type')->disabled(),
            TextInput::make('causer_id')->disabled(),
            KeyValue::make('properties')
                ->disabled()
                ->label('Properties (changes)'),
            TextInput::make('created_at')->disabled(),
        ]);
    }
}
