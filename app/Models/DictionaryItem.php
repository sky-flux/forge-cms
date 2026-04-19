<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Dictionary;
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

    protected static function booted(): void
    {
        $bust = function (DictionaryItem $item): void {
            $code = $item->type()->value('code');
            if ($code !== null) {
                Dictionary::forget($code);
            }

            $originalTypeId = $item->getOriginal('type_id');
            if ($originalTypeId !== null && (int) $originalTypeId !== (int) $item->type_id) {
                $originalCode = DictionaryType::query()->where('id', $originalTypeId)->value('code');
                if ($originalCode !== null) {
                    Dictionary::forget($originalCode);
                }
            }
        };

        static::saved($bust);
        static::deleted($bust);
    }
}
