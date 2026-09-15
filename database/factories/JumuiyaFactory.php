<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<\App\Models\Jumuiya> */
class JumuiyaFactory extends Factory
{
    public function definition(): array
    {
        return [
            'zone_id' => \App\Models\Zone::factory(),
            'parish_id' => fn (array $attributes) => \App\Models\Zone::findOrFail($attributes['zone_id'])->parish_id,
            'code' => strtoupper(fake()->unique()->bothify('??-########')),
            'name' => fake()->words(3, true),
            'status' => 'active',
            'verification_status' => 'verified',
            'verified_at' => now(),
        ];
    }
}
