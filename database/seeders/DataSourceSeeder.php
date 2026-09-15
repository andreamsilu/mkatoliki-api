<?php

namespace Database\Seeders;

use App\Models\DataSource;
use Illuminate\Database\Seeder;

class DataSourceSeeder extends Seeder
{
    public function run(): void
    {
        $snapshot = json_decode(file_get_contents(__DIR__.'/tec-directory-2020.json'), true, flags: JSON_THROW_ON_ERROR);
        $source = $snapshot['source'];

        DataSource::firstOrCreate(['name' => $source['name'], 'version' => $source['version']], [
            'type' => $source['type'],
            'publisher' => $source['publisher'],
            'reference' => $source['reference']."\n".$source['transcription_reference'],
            'description' => 'Historical 2020 baseline. Requires review and verification before publication. '
                .'Printed page references and coverage gaps: database/seeders/tec-directory-2020.json. '
                .'Public transcription retrieved '.$source['retrieved_at'].'; publisher PDF unavailable. '
                .'Transcription SHA-256: '.$source['transcription_sha256'],
        ]);
    }
}
