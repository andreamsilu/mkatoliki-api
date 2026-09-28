<?php

namespace Database\Factories;

use App\Models\ContributionCampaign;
use App\Models\Parish;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ContributionCampaign> */
class ContributionCampaignFactory extends Factory
{
    public function definition(): array
    {
        return [
            'parish_id' => Parish::factory(),
            'title' => fake()->sentence(3),
            'description' => fake()->sentence(),
            'target_amount' => fake()->numberBetween(50000, 500000),
            'starts_on' => now()->subWeek()->toDateString(),
            'ends_on' => now()->addMonth()->toDateString(),
            'status' => 'active',
        ];
    }
}
