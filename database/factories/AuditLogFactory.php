<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<\App\Models\AuditLog> */
class AuditLogFactory extends Factory
{
    public function definition(): array
    {
        return ['action' => 'created', 'entity_type' => 'parishes', 'entity_id' => \App\Models\Parish::factory()];
    }
}
