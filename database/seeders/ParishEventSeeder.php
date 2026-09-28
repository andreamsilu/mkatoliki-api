<?php

namespace Database\Seeders;

use App\Models\ParishEvent;
use Illuminate\Database\Seeder;

class ParishEventSeeder extends Seeder
{
    public function run(): void
    {
        ParishEvent::factory()->count(3)->create();
    }
}
