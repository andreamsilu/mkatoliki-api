<?php

namespace Database\Factories;

use App\Models\Member;
use App\Models\MemberNotification;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<MemberNotification> */
class MemberNotificationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'member_id' => Member::factory(),
            'title' => fake()->sentence(4),
            'message' => fake()->paragraph(),
            'type' => fake()->randomElement(['announcement', 'event', 'jumuiya']),
            'published_at' => now(),
            'read_at' => null,
        ];
    }
}
