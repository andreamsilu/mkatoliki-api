<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<\App\Models\Permission> */
class PermissionFactory extends Factory
{
    public function definition(): array
    {
        return ['name' => fake()->unique()->slug()];
    }
}
