<?php

declare(strict_types=1);

namespace App\Filament\Resources\DictionaryTypes\Pages;

use App\Filament\Resources\DictionaryTypes\DictionaryTypeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListDictionaryTypes extends ListRecords
{
    protected static string $resource = DictionaryTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
