<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\DictionaryTypeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DictionaryType extends Model
{
    /** @use HasFactory<DictionaryTypeFactory> */
    use HasFactory;

    protected $fillable = ['code', 'name', 'remark'];

    public function items(): HasMany
    {
        return $this->hasMany(DictionaryItem::class, 'type_id')
            ->orderBy('sort')
            ->orderBy('id');
    }
}
