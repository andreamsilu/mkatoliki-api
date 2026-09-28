<?php

namespace Database\Seeders;

use App\Models\ParishProject;
use Illuminate\Database\Seeder;

class ParishProjectSeeder extends Seeder
{
    public function run(): void
    {
        ParishProject::factory()->count(3)->create();
    }
}
