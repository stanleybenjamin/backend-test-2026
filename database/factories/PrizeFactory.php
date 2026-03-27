<?php

namespace Database\Factories;

use App\Models\Campaign;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Prize>
 */
class PrizeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->word,
            'campaign_id' => Campaign::factory(),
            'segment' => $this->faker->randomElement(['low', 'med', 'high']),
            'weight' => $this->faker->numberBetween(1, 10),
            'daily_limit' => $this->faker->numberBetween(1, 100),
            'starts_at' => now(),
            'ends_at' => now()->addWeek(),
        ];
    }
}
