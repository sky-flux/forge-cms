<?php

declare(strict_types=1);

namespace App\Filament\Resources\Categories\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class CategoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(100),
                TextInput::make('slug')
                    ->disabled()
                    ->dehydrated(true)
                    ->helperText('Auto-generated from name'),
                Select::make('parent_id')
                    ->relationship('parent', 'name')
                    ->searchable()
                    ->placeholder('Top-level'),
                Textarea::make('description')
                    ->maxLength(500)
                    ->columnSpanFull(),
                TextInput::make('sort_order')
                    ->numeric()
                    ->default(0),
            ]);
    }
}
