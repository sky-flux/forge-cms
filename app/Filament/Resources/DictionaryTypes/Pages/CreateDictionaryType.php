<?php

declare(strict_types=1);

namespace App\Filament\Resources\DictionaryTypes\Pages;

use App\Filament\Resources\DictionaryTypes\DictionaryTypeResource;
use Filament\Resources\Pages\CreateRecord;

class CreateDictionaryType extends CreateRecord
{
    protected static string $resource = DictionaryTypeResource::class;
}
