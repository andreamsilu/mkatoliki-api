<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<\App\Models\DataSource> */
class DataSourceFactory extends Factory
{
    public function definition(): array
    {
        return ['name' => fake()->unique()->company(), 'type' => 'official_directory', 'version' => '2020'];
    }
}
