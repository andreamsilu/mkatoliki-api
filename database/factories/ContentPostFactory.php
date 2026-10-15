<?php

namespace Database\Factories;

use App\Models\ContentCategory;
use App\Models\ContentPost;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ContentPost> */
class ContentPostFactory extends Factory
{
    protected $model = ContentPost::class;

    public function definition(): array
    {
        return ['slug' => fake()->unique()->slug(), 'content_type' => 'teaching', 'format' => 'article',
            'status' => 'draft', 'author_id' => User::factory(), 'rights_status' => 'original', 'source_type' => 'editorial'];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (ContentPost $post): void {
            $category = ContentCategory::firstOrCreate(['slug' => 'mafundisho'], ['name' => 'Mafundisho', 'icon' => 'church']);
            $post->categories()->attach($category);
            $post->translations()->create(['locale' => 'sw', 'title' => 'Mafundisho ya majaribio', 'summary' => 'Muhtasari wa majaribio.', 'body' => 'Maudhui haya ni ya majaribio ya programu pekee.']);
        });
    }

    public function published(): static
    {
        return $this->state(fn () => ['status' => 'published', 'reviewer_id' => User::factory(), 'published_at' => now()->subDay()]);
    }
}
