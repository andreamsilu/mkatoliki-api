<?php

namespace Database\Seeders;

use App\Models\ContentCategory;
use Illuminate\Database\Seeder;

class ContentCategorySeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            ['Neno la Leo', 'neno-la-leo', 'Tafakari ya Maandiko na hatua ya siku.', 'book-open'],
            ['Mafundisho', 'mafundisho', 'Imani, sakramenti na mapokeo ya Kanisa.', 'church'],
            ['Sala', 'sala', 'Sala na miongozo ya kusali.', 'heart-handshake'],
            ['Nyimbo za Kanisa', 'nyimbo-za-kanisa', 'Nyimbo na rekodi zilizoidhinishwa.', 'music'],
            ['Historia ya Kanisa', 'historia-ya-kanisa', 'Historia ya Kanisa Tanzania na duniani.', 'landmark'],
            ['Maswali ya Imani', 'maswali-ya-imani', 'Majibu yaliyohakikiwa kuhusu imani.', 'circle-help'],
            ['Maisha ya Kikristo', 'maisha-ya-kikristo', 'Familia, vijana, huduma na maisha ya imani.', 'users'],
            ['Habari za Parokia', 'habari-za-parokia', 'Habari na matukio yaliyopitiwa na wahariri.', 'newspaper'],
        ] as $order => [$name, $slug, $description, $icon]) {
            ContentCategory::firstOrCreate(['slug' => $slug], compact('name', 'description', 'icon') + ['sort_order' => $order]);
        }
    }
}
