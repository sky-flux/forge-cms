<?php

declare(strict_types=1);

use App\Models\DictionaryItem;
use App\Models\DictionaryType;
use App\Support\Dictionary;
use Illuminate\Support\Facades\Cache;

test('items() returns enabled items for a type ordered by sort', function (): void {
    $type = DictionaryType::factory()->create(['code' => 'priority']);
    DictionaryItem::factory()->for($type, 'type')->create(['label' => 'Low',  'value' => 'low',  'sort' => 30, 'status' => true]);
    DictionaryItem::factory()->for($type, 'type')->create(['label' => 'High', 'value' => 'high', 'sort' => 10, 'status' => true]);
    DictionaryItem::factory()->for($type, 'type')->create(['label' => 'Off',  'value' => 'off',  'sort' => 20, 'status' => false]);

    $items = Dictionary::items('priority');

    expect($items->pluck('value')->all())->toBe(['high', 'low']);
});

test('items() returns an empty collection for an unknown code', function (): void {
    expect(Dictionary::items('does-not-exist')->all())->toBe([]);
});

test('items() result is cached and bust on item save', function (): void {
    Cache::flush();
    $type = DictionaryType::factory()->create(['code' => 'channel']);
    DictionaryItem::factory()->for($type, 'type')->create(['value' => 'email', 'status' => true]);

    Dictionary::items('channel');
    expect(Cache::has('dict.items.channel'))->toBeTrue();

    DictionaryItem::factory()->for($type, 'type')->create(['value' => 'sms', 'status' => true]);

    expect(Cache::has('dict.items.channel'))->toBeFalse()
        ->and(Dictionary::items('channel')->pluck('value')->sort()->values()->all())->toBe(['email', 'sms']);
});

test('default() returns the first item with is_default=true, or null', function (): void {
    $type = DictionaryType::factory()->create(['code' => 'visibility']);
    DictionaryItem::factory()->for($type, 'type')->create(['value' => 'public',  'is_default' => true,  'status' => true]);
    DictionaryItem::factory()->for($type, 'type')->create(['value' => 'private', 'is_default' => false, 'status' => true]);

    expect(Dictionary::default('visibility')?->value)->toBe('public')
        ->and(Dictionary::default('does-not-exist'))->toBeNull();
});
