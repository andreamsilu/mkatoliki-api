<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<\App\Models\Parish> */
class ParishFactory extends Factory
{
    public function definition(): array
    {
        return [
            'deanery_id' => \App\Models\Deanery::factory(),
            'code' => strtoupper(fake()->unique()->bothify('??-########')),
            'name' => fake()->words(3, true),
            'status' => 'active',
            'verification_status' => 'verified',
            'verified_at' => now(),
        ];
    }
}
