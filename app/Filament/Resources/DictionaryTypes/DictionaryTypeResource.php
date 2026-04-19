<?php

declare(strict_types=1);

namespace App\Filament\Resources\DictionaryTypes;

use App\Filament\Resources\DictionaryTypes\Pages\CreateDictionaryType;
use App\Filament\Resources\DictionaryTypes\Pages\EditDictionaryType;
use App\Filament\Resources\DictionaryTypes\Pages\ListDictionaryTypes;
use App\Filament\Resources\DictionaryTypes\RelationManagers\ItemsRelationManager;
use App\Filament\Resources\DictionaryTypes\Schemas\DictionaryTypeForm;
use App\Filament\Resources\DictionaryTypes\Tables\DictionaryTypesTable;
use App\Models\DictionaryType;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class DictionaryTypeResource extends Resource
{
    protected static ?string $model = DictionaryType::class;

    protected static string|\UnitEnum|null $navigationGroup = '系统';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = '字典';

    protected static ?string $modelLabel = '字典类型';

    protected static ?string $pluralModelLabel = '字典类型';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    // TEMPORARY: replaced by DictionaryTypePolicy in Task 5 of plan 2026-04-19-system-dictionary.md
    public static function canAccess(): bool
    {
        return true;
    }

    public static function form(Schema $schema): Schema
    {
        return DictionaryTypeForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DictionaryTypesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            ItemsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDictionaryTypes::route('/'),
            'create' => CreateDictionaryType::route('/create'),
            'edit' => EditDictionaryType::route('/{record}/edit'),
        ];
    }
}
