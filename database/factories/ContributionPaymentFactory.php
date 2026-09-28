<?php

namespace Database\Factories;

use App\Models\ContributionCampaign;
use App\Models\ContributionPayment;
use App\Models\Member;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<ContributionPayment> */
class ContributionPaymentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'member_id' => Member::factory(),
            'contribution_campaign_id' => ContributionCampaign::factory(),
            'amount' => fake()->numberBetween(1000, 50000),
            'payment_method' => 'mobile_money',
            'reference' => 'MKT-'.Str::upper(fake()->unique()->bothify('########??')),
            'status' => 'confirmed',
            'requested_at' => now()->subDay(),
            'confirmed_at' => now(),
            'receipt_number' => 'RCT-'.Str::upper(fake()->unique()->bothify('########??')),
        ];
    }
}
