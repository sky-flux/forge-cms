<?php

declare(strict_types=1);

namespace App\Filament\Resources\Users\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->required()
                ->maxLength(255),

            TextInput::make('email')
                ->email()
                ->required()
                ->unique(ignoreRecord: true)
                ->maxLength(255),

            DateTimePicker::make('email_verified_at')
                ->label('Email verified at')
                ->nullable(),

            // NOTE: do NOT call Hash::make here. User model declares
            // 'password' => 'hashed' in casts() (app/Models/User.php),
            // which hashes on assignment. Manual Hash::make would double-bcrypt.
            TextInput::make('password')
                ->password()
                ->revealable()
                ->dehydrated(fn (?string $state): bool => filled($state))
                ->required(fn (string $operation): bool => $operation === 'create')
                ->confirmed()
                ->minLength(8),

            TextInput::make('password_confirmation')
                ->password()
                ->revealable()
                ->dehydrated(false)
                ->required(fn (string $operation): bool => $operation === 'create'),

            Select::make('roles')
                ->relationship(
                    'roles',
                    'name',
                    fn (Builder $query) => auth()->user()?->hasRole('super_admin')
                        ? $query
                        : $query->where('name', '!=', 'super_admin'),
                )
                ->multiple()
                ->preload()
                ->searchable(),
        ]);
    }
}
