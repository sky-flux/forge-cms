<?php

declare(strict_types=1);

use App\Models\DictionaryItem;
use App\Models\DictionaryType;
use Illuminate\Database\QueryException;

test('a dictionary type has many items ordered by sort then id', function (): void {
    $type = DictionaryType::factory()->create(['code' => 'demo']);
    $b = DictionaryItem::factory()->for($type, 'type')->create(['sort' => 20, 'label' => 'B']);
    $a = DictionaryItem::factory()->for($type, 'type')->create(['sort' => 10, 'label' => 'A']);

    expect($type->items()->pluck('label')->all())->toBe(['A', 'B']);
});

test('a dictionary type code is unique', function (): void {
    DictionaryType::factory()->create(['code' => 'duplicate']);

    expect(fn () => DictionaryType::factory()->create(['code' => 'duplicate']))
        ->toThrow(QueryException::class);
});

test('deleting a type cascades to its items', function (): void {
    $type = DictionaryType::factory()->create();
    DictionaryItem::factory()->count(3)->for($type, 'type')->create();

    $type->delete();

    expect(DictionaryItem::where('type_id', $type->id)->count())->toBe(0);
});
