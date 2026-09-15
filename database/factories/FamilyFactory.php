<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<\App\Models\Family> */
class FamilyFactory extends Factory
{
    public function definition(): array
    {
        return [
            'parish_id' => \App\Models\Parish::factory(),
            'family_code' => strtoupper(fake()->unique()->bothify('??-########')),
            'family_name' => fake()->words(3, true),
            'status' => 'active',
        ];
    }
}
