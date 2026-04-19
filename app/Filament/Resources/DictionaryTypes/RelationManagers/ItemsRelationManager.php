<?php

declare(strict_types=1);

namespace App\Filament\Resources\DictionaryTypes\RelationManagers;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Validation\Rule;

class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('label')
                ->required()
                ->maxLength(128),

            TextInput::make('value')
                ->required()
                ->maxLength(128)
                ->rule(fn () => Rule::unique('dictionary_items', 'value')
                    ->where('type_id', $this->getOwnerRecord()->id)
                    ->ignore($this->getMountedTableActionRecord()?->id)),

            TextInput::make('sort')
                ->numeric()
                ->default(0),

            Toggle::make('is_default')->default(false),
            Toggle::make('status')->default(true)->label('Enabled'),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('label')
            ->columns([
                TextColumn::make('label')->sortable()->searchable(),
                TextColumn::make('value')->sortable()->searchable(),
                TextColumn::make('sort')->sortable(),
                IconColumn::make('is_default')->boolean(),
                IconColumn::make('status')->boolean()->label('Enabled'),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('sort');
    }
}
