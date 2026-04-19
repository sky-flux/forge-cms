<?php

declare(strict_types=1);

namespace App\Filament\Resources\DictionaryTypes\Pages;

use App\Filament\Resources\DictionaryTypes\DictionaryTypeResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditDictionaryType extends EditRecord
{
    protected static string $resource = DictionaryTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
