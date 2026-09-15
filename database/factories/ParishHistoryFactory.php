<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<\App\Models\ParishHistory> */
class ParishHistoryFactory extends Factory
{
    public function definition(): array
    {
        return ['parish_id' => \App\Models\Parish::factory(), 'old_deanery_id' => \App\Models\Deanery::factory(), 'new_deanery_id' => \App\Models\Deanery::factory(), 'old_diocese_id' => fn (array $attributes) => \App\Models\Deanery::findOrFail($attributes['old_deanery_id'])->diocese_id, 'new_diocese_id' => fn (array $attributes) => \App\Models\Deanery::findOrFail($attributes['new_deanery_id'])->diocese_id, 'effective_date' => today(), 'reason' => 'Administrative transfer', 'source_id' => \App\Models\DataSource::factory()];
    }
}
