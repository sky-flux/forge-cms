<?php

declare(strict_types=1);

namespace App\Filament\Resources\Comments\Schemas;

use App\Enums\CommentStatus;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class CommentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Textarea::make('body')
                ->rows(6)
                ->required()
                ->columnSpanFull()
                ->helperText('Plain text only. body_html regenerates automatically on save via the model observer.'),
            Select::make('status')
                ->options(CommentStatus::class)
                ->required(),
            DateTimePicker::make('approved_at')
                ->disabled()
                ->helperText('Set automatically when status changes to approved via the row action.'),
            TextInput::make('guest_name')->disabled(),
            TextInput::make('guest_email')->disabled(),
            Textarea::make('user_agent')->disabled()->rows(2),
        ]);
    }
}
