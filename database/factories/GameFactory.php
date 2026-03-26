<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Game>
 */
class GameFactory extends Factory
{
    public function definition(): array
    {

        return [
            'account' => $this->faker->userName(),
            'segment' => $this->faker->randomElement(['low', 'med', 'high']),
            'prize_id' => null, // will be set in the seeder
            'finished_at' => now()->subDays(random_int(1, 10)),
        ];
    }
}
