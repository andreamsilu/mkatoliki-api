<?php

namespace Database\Factories;

use App\Models\Parish;
use App\Models\ParishEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ParishEvent>
 */
class ParishEventFactory extends Factory
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
            'title' => fake()->sentence(4),
            'starts_at' => now()->addDays(fake()->numberBetween(1, 30)),
            'ends_at' => null,
            'venue' => fake()->streetName(),
            'description' => fake()->paragraph(),
            'is_published' => true,
        ];
    }
}
