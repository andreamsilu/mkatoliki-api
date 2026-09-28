<?php

namespace Database\Seeders;

use App\Models\DataSource;
use App\Models\Parish;
use App\Models\ParishAnnouncement;
use Illuminate\Database\Seeder;
use RuntimeException;

class ParishContentSeeder extends Seeder
{
    public function run(): void
    {
        $source = DataSource::query()
            ->where('name', 'Confirmed Dar es Salaam parish-deanery mapping')
            ->where('version', '2026-09-26')
            ->first();

        if (! $source) {
            throw new RuntimeException('Run DarEsSalaamDirectorySeeder before ParishContentSeeder.');
        }

        Parish::query()
            ->where('source_id', $source->id)
            ->where('status', 'active')
            ->each(function (Parish $parish): void {
                ParishAnnouncement::query()->firstOrCreate(
                    [
                        'parish_id' => $parish->id,
                        'title' => 'Karibu katika huduma za kidijitali',
                        'category' => 'Mfumo',
                    ],
                    [
                        'summary' => "Taarifa rasmi za {$parish->name}, ikiwemo ratiba za Misa, matangazo, matukio na miradi, zitachapishwa hapa baada ya kuthibitishwa na parokia.",
                        'published_at' => now(),
                        'is_published' => true,
                    ],
                );
            });
    }
}
