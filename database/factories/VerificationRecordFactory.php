<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<\App\Models\VerificationRecord> */
class VerificationRecordFactory extends Factory
{
    public function definition(): array
    {
        return ['entity_type' => 'parishes', 'entity_id' => \App\Models\Parish::factory(), 'source_id' => \App\Models\DataSource::factory(), 'status' => 'pending', 'verified_by' => \App\Models\User::factory(), 'verified_at' => now()];
    }
}
