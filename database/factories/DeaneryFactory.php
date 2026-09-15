<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<\App\Models\Deanery> */
class DeaneryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'diocese_id' => \App\Models\Diocese::factory(),
            'code' => strtoupper(fake()->unique()->bothify('??-########')),
            'name' => fake()->words(3, true),
            'status' => 'active',
            'verification_status' => 'verified',
            'verified_at' => now(),
        ];
    }
}
