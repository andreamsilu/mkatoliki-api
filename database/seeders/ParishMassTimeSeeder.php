<?php

namespace Database\Seeders;

use App\Models\ParishMassTime;
use Illuminate\Database\Seeder;

class ParishMassTimeSeeder extends Seeder
{
    public function run(): void
    {
        ParishMassTime::factory()->count(3)->create();
    }
}
