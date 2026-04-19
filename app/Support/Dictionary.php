<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\DictionaryItem;
use App\Models\DictionaryType;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

final class Dictionary
{
    public const string CACHE_PREFIX = 'dict.items.';

    /**
     * Enabled items for the given type, ordered by sort then id.
     *
     * @return Collection<int, DictionaryItem>
     */
    public static function items(string $code): Collection
    {
        return Cache::rememberForever(
            self::cacheKey($code),
            fn (): Collection => DictionaryType::query()
                ->where('code', $code)
                ->first()
                ?->items()
                ->where('status', true)
                ->get() ?? collect(),
        );
    }

    public static function default(string $code): ?DictionaryItem
    {
        return self::items($code)->firstWhere('is_default', true);
    }

    public static function cacheKey(string $code): string
    {
        return self::CACHE_PREFIX.$code;
    }

    public static function forget(string $code): void
    {
        Cache::forget(self::cacheKey($code));
    }
}
