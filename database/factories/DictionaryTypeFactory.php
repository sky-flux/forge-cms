<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\DictionaryType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DictionaryType>
 */
class DictionaryTypeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->slug(2),
            'name' => fake()->words(2, true),
            'remark' => fake()->optional()->sentence(),
        ];
    }
}
