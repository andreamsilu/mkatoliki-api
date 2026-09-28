<?php

namespace Database\Factories;

use App\Models\Parish;
use App\Models\ParishProject;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ParishProject>
 */
class ParishProjectFactory extends Factory
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
            'subtitle' => fake()->sentence(6),
            'progress_percentage' => fake()->numberBetween(0, 100),
            'is_published' => true,
        ];
    }
}
