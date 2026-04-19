<?php

declare(strict_types=1);

namespace App\Filament\Resources\ActivityLog\Tables;

use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Spatie\Activitylog\Models\Activity;

class ActivityLogTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('log_name')->label('日志')->sortable(),
                TextColumn::make('description')->label('描述')->searchable(),
                TextColumn::make('causer.name')->label('操作人'),
                TextColumn::make('subject_type')
                    ->label('对象')
                    ->formatStateUsing(fn (?string $state): string => $state ? class_basename($state) : '—'),
                TextColumn::make('subject_id')->label('对象 ID')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->filters([
                SelectFilter::make('log_name')
                    ->options(fn (): array => Activity::query()
                        ->distinct()
                        ->pluck('log_name', 'log_name')
                        ->filter()
                        ->toArray()),
                SelectFilter::make('subject_type')
                    ->options(fn (): array => Activity::query()
                        ->distinct()
                        ->pluck('subject_type', 'subject_type')
                        ->filter()
                        ->mapWithKeys(fn (string $value): array => [$value => class_basename($value)])
                        ->toArray()),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
