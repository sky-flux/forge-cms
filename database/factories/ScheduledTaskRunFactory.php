<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ScheduledTaskRun;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ScheduledTaskRun>
 */
class ScheduledTaskRunFactory extends Factory
{
    public function definition(): array
    {
        $started = now()->subMinutes($this->faker->numberBetween(1, 60 * 24));

        return [
            'command' => 'artisan:'.$this->faker->unique()->word(),
            'started_at' => $started,
            'finished_at' => $started->copy()->addSeconds($this->faker->numberBetween(1, 120)),
            'exit_code' => 0,
            'output' => $this->faker->sentence(),
        ];
    }
}
