<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<\App\Models\Member> */
class MemberFactory extends Factory
{
    public function definition(): array
    {
        return [
            'parish_id' => \App\Models\Parish::factory(),
            'member_code' => strtoupper(fake()->unique()->bothify('??-########')),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'gender' => 'female',
            'status' => 'active',
        ];
    }
}
