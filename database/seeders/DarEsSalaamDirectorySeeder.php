<?php

namespace Database\Seeders;

use App\Models\DataSource;
use App\Models\Deanery;
use App\Models\Diocese;
use App\Models\Parish;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use LogicException;

class DarEsSalaamDirectorySeeder extends Seeder
{
    public function run(): void
    {
        $dataset = json_decode(file_get_contents(__DIR__.'/dar-es-salaam-directory-2026.json'), true, flags: JSON_THROW_ON_ERROR);

        DB::transaction(function () use ($dataset): void {
            $sourceDefinition = $dataset['source'];
            $source = DataSource::query()->firstOrCreate(
                Arr::only($sourceDefinition, ['name', 'version']),
                Arr::except($sourceDefinition, ['name', 'version']),
            );
            $diocese = Diocese::query()->where('code', $dataset['diocese_code'])->firstOrFail();
            $deaneryIds = [];

            foreach ($dataset['deaneries'] as $row) {
                $deanery = Deanery::query()->firstOrCreate(['code' => $row['code']], [
                    'diocese_id' => $diocese->id,
                    'name' => $row['name'],
                    'name_en' => $row['name_en'],
                    'status' => 'active',
                    'source_id' => $source->id,
                    'verification_status' => 'verified',
                    'verified_at' => now(),
                ]);

                if ((int) $deanery->diocese_id !== (int) $diocese->id) {
                    throw new LogicException('The deanery code '.$row['code'].' is already assigned to another diocese.');
                }

                $deaneryIds[$row['code']] = $deanery->id;
            }

            foreach ($dataset['parishes'] as $row) {
                $deaneryId = $deaneryIds[$row['parent_code']] ?? null;
                if (! $deaneryId) {
                    throw new LogicException('Missing Dar es Salaam deanery for '.$row['code'].'.');
                }

                Parish::query()->withTrashed()->firstOrCreate(['code' => $row['code']], [
                    'deanery_id' => $deaneryId,
                    'name' => $row['name'],
                    'name_en' => $row['name_en'],
                    'patron_saint' => $row['patron_saint'],
                    'status' => 'active',
                    'source_id' => $source->id,
                    'verification_status' => 'verified',
                    'verified_at' => now(),
                ]);
            }
        });

        $this->command?->info('Confirmed Dar es Salaam directory update loaded: four deaneries and 41 parish mappings.');
    }
}
