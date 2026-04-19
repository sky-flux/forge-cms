<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\DictionaryItem;
use App\Models\DictionaryType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DictionaryItem>
 */
class DictionaryItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type_id' => DictionaryType::factory(),
            'label' => fake()->word(),
            'value' => fake()->unique()->slug(1),
            'sort' => fake()->numberBetween(0, 100),
            'is_default' => false,
            'status' => true,
        ];
    }
}
