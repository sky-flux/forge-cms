<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\DictionaryItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DictionaryItem extends Model
{
    /** @use HasFactory<DictionaryItemFactory> */
    use HasFactory;

    protected $fillable = ['type_id', 'label', 'value', 'sort', 'is_default', 'status'];

    protected function casts(): array
    {
        return [
            'sort' => 'integer',
            'is_default' => 'boolean',
            'status' => 'boolean',
        ];
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(DictionaryType::class, 'type_id');
    }
}
