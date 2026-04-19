<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\DictionaryItem;
use App\Models\DictionaryType;
use Illuminate\Database\Seeder;

class DictionarySeeder extends Seeder
{
    public function run(): void
    {
        $visibility = DictionaryType::firstOrCreate(
            ['code' => 'post_visibility'],
            ['name' => 'Post Visibility', 'remark' => 'Who can see a post'],
        );

        foreach ([
            ['label' => 'Public',  'value' => 'public',  'sort' => 10, 'is_default' => true],
            ['label' => 'Members', 'value' => 'members', 'sort' => 20, 'is_default' => false],
            ['label' => 'Private', 'value' => 'private', 'sort' => 30, 'is_default' => false],
        ] as $row) {
            DictionaryItem::firstOrCreate(
                ['type_id' => $visibility->id, 'value' => $row['value']],
                $row + ['status' => true],
            );
        }
    }
}
