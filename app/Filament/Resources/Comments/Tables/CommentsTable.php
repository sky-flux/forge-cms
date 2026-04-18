<?php

declare(strict_types=1);

namespace App\Filament\Resources\Comments\Tables;

use App\Enums\CommentStatus;
use App\Models\Comment;
use App\Models\Page;
use App\Models\Post;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class CommentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('body')
                    ->limit(100)
                    ->wrap()
                    ->searchable(),
                TextColumn::make('author')
                    ->label('Author')
                    ->state(fn (Comment $record): string => $record->user?->name ?? $record->guest_name ?? '(anonymous)')
                    ->description(fn (Comment $record): ?string => $record->guest_email),
                TextColumn::make('commentable_type')
                    ->label('On')
                    ->formatStateUsing(fn (string $state): string => class_basename($state))
                    ->badge(),
                TextColumn::make('status')
                    ->badge(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options(CommentStatus::class),
                SelectFilter::make('commentable_type')
                    ->options([
                        Post::class => 'Post',
                        Page::class => 'Page',
                    ])
                    ->label('Content type'),
            ])
            ->recordActions([
                ActionGroup::make([
                    Action::make('approve')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->visible(fn (Comment $r): bool => $r->status !== CommentStatus::Approved)
                        ->action(fn (Comment $r) => $r->update([
                            'status' => CommentStatus::Approved,
                            'approved_at' => now(),
                        ])),
                    Action::make('markSpam')
                        ->icon('heroicon-o-exclamation-triangle')
                        ->color('warning')
                        ->visible(fn (Comment $r): bool => $r->status !== CommentStatus::Spam)
                        ->action(fn (Comment $r) => $r->update(['status' => CommentStatus::Spam])),
                    Action::make('trash')
                        ->icon('heroicon-o-trash')
                        ->color('danger')
                        ->visible(fn (Comment $r): bool => $r->status !== CommentStatus::Trash)
                        ->action(fn (Comment $r) => $r->update(['status' => CommentStatus::Trash])),
                    EditAction::make(),
                    DeleteAction::make(),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('approveAll')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->action(fn ($records) => $records->each->update([
                            'status' => CommentStatus::Approved,
                            'approved_at' => now(),
                        ]))
                        ->deselectRecordsAfterCompletion(),
                    BulkAction::make('markSpamAll')
                        ->icon('heroicon-o-exclamation-triangle')
                        ->color('warning')
                        ->action(fn ($records) => $records->each->update(['status' => CommentStatus::Spam]))
                        ->deselectRecordsAfterCompletion(),
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
