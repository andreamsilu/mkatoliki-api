<?php

namespace Database\Factories;

use App\Models\Parish;
use App\Models\ParishMassTime;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ParishMassTime>
 */
class ParishMassTimeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'parish_id' => Parish::factory(),
            'day_label' => fake()->randomElement(['Weekdays', 'Saturday', 'Sunday']),
            'time_label' => fake()->randomElement(['6:30 AM', '7:00 AM · 9:00 AM', '6:00 PM']),
            'display_order' => fake()->numberBetween(0, 5),
            'is_published' => true,
        ];
    }
}
