<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<\App\Models\ImportBatch> */
class ImportBatchFactory extends Factory
{
    public function definition(): array
    {
        return ['source_id' => \App\Models\DataSource::factory(), 'created_by' => \App\Models\User::factory(), 'entity_type' => 'parishes', 'checksum' => hash('sha256', fake()->uuid()), 'rows' => [], 'report' => []];
    }
}
