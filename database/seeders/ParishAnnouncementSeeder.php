<?php

namespace Database\Seeders;

use App\Models\ParishAnnouncement;
use Illuminate\Database\Seeder;

class ParishAnnouncementSeeder extends Seeder
{
    public function run(): void
    {
        ParishAnnouncement::factory()->count(3)->create();
    }
}
