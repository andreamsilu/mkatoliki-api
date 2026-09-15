<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<\App\Models\Ministry> */
class MinistryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'parish_id' => \App\Models\Parish::factory(),
            'name' => fake()->words(3, true),
            'code' => strtoupper(fake()->unique()->bothify('??-########')),
            'status' => 'active',
            'verification_status' => 'verified',
            'verified_at' => now(),
        ];
    }
}
