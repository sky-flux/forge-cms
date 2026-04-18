<?php

declare(strict_types=1);

namespace App\Filament\Resources\Posts\Schemas;

use App\Enums\PostStatus;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class PostForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('title')
                    ->required()
                    ->maxLength(255),
                TextInput::make('slug')
                    ->disabled()
                    ->dehydrated(true)
                    ->helperText('Auto-generated from title on save'),
                Select::make('status')
                    ->options(PostStatus::class)
                    ->required()
                    ->default(PostStatus::Draft),
                DateTimePicker::make('published_at'),
                Select::make('user_id')
                    ->relationship('user', 'name')
                    ->required()
                    ->searchable()
                    ->preload(),
                Textarea::make('excerpt')
                    ->maxLength(500)
                    ->columnSpanFull(),
                RichEditor::make('body_html')
                    ->required()
                    ->columnSpanFull(),
                TextInput::make('seo_title')
                    ->maxLength(255),
                TextInput::make('seo_description')
                    ->maxLength(500),
                Toggle::make('is_comments_enabled')
                    ->default(true),
                SpatieMediaLibraryFileUpload::make('featured')
                    ->collection('featured')
                    ->image(),
            ]);
    }
}
