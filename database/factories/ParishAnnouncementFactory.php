<?php

namespace Database\Factories;

use App\Models\Parish;
use App\Models\ParishAnnouncement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ParishAnnouncement>
 */
class ParishAnnouncementFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'parish_id' => Parish::factory(),
            'title' => fake()->sentence(5),
            'summary' => fake()->paragraph(),
            'category' => fake()->randomElement(['Parish', 'Faith', 'Community']),
            'published_at' => now()->subDays(fake()->numberBetween(0, 14)),
            'is_published' => true,
        ];
    }
}
