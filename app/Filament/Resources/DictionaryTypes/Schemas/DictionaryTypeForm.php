<?php

declare(strict_types=1);

namespace App\Filament\Resources\DictionaryTypes\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class DictionaryTypeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('code')
                ->required()
                ->maxLength(64)
                ->alphaDash()
                ->unique(ignoreRecord: true)
                ->helperText('Identifier used in code, e.g. post_visibility.'),

            TextInput::make('name')
                ->required()
                ->maxLength(128),

            Textarea::make('remark')
                ->maxLength(255)
                ->rows(2),
        ]);
    }
}
