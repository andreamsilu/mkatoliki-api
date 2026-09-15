<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<\App\Models\Diocese> */
class DioceseFactory extends Factory
{
    public function definition(): array
    {
        return [
            'ecclesiastical_province_id' => \App\Models\EcclesiasticalProvince::factory(),
            'code' => strtoupper(fake()->unique()->bothify('??-########')),
            'name' => fake()->words(3, true),
            'type' => 'diocese',
            'status' => 'active',
            'verification_status' => 'verified',
            'verified_at' => now(),
        ];
    }
}
