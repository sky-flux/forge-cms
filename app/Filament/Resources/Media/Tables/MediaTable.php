<?php

declare(strict_types=1);

namespace App\Filament\Resources\Media\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class MediaTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('preview')
                    ->label('Preview')
                    ->state(fn (Media $record): string => $record->getFullUrl())
                    ->square(),
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('collection_name')
                    ->label('Collection')
                    ->sortable(),
                TextColumn::make('mime_type')
                    ->label('Mime')
                    ->sortable(),
                TextColumn::make('size')
                    ->formatStateUsing(fn (int $state): string => round($state / 1024, 1).' KB')
                    ->sortable(),
                TextColumn::make('model_type')
                    ->label('Attached to')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('mime_type')
                    ->label('Mime category')
                    ->options([
                        'image/' => 'Images',
                        'video/' => 'Videos',
                        'application/pdf' => 'PDFs',
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $data['value']
                        ? $query->where('mime_type', 'like', $data['value'].'%')
                        : $query),
                SelectFilter::make('collection_name')
                    ->label('Collection')
                    ->options(fn (): array => Media::query()
                        ->distinct()
                        ->pluck('collection_name', 'collection_name')
                        ->toArray()),
            ])
            ->recordActions([
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
