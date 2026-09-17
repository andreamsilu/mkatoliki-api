<?php

namespace Database\Seeders;

use App\Models\DataSource;
use App\Models\Deanery;
use App\Models\Diocese;
use App\Models\DirectoryEntity;
use App\Models\EcclesiasticalProvince;
use App\Models\Parish;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use LogicException;

class TecDirectorySeeder extends Seeder
{
    public function run(): void
    {
        $snapshot = json_decode(file_get_contents(__DIR__.'/tec-directory-2020.json'), true, flags: JSON_THROW_ON_ERROR);

        DB::transaction(function () use ($snapshot): void {
            $this->call(DataSourceSeeder::class);
            $source = DataSource::query()->where('name', $snapshot['source']['name'])
                ->where('version', $snapshot['source']['version'])->firstOrFail();

            $provinceIds = $this->seedRows(EcclesiasticalProvince::class, $snapshot['provinces'], $source->id);
            $dioceseIds = $this->seedRows(Diocese::class, $snapshot['dioceses'], $source->id, 'ecclesiastical_province_id', $provinceIds);
            $deaneryIds = $this->seedRows(Deanery::class, $snapshot['deaneries'], $source->id, 'diocese_id', $dioceseIds);
            $this->seedRows(Parish::class, $snapshot['parishes'], $source->id, 'deanery_id', $deaneryIds);
        });

        $this->command?->info('TEC 2020 baseline loaded and published; existing records and corrections preserved.');
        $this->command?->line('Parish mapping coverage: Arusha, Kahama, Moshi, Sumbawanga, Tabora and Tanga. See the snapshot coverage notes for gaps.');
    }

    /**
     * @param  class-string<DirectoryEntity>  $modelClass
     * @param  list<array{code: string, name: string, name_en: string, parent_code?: string, type?: string, source_pages: list<int>}>  $rows
     * @param  array<string, int>  $parentIds
     * @return array<string, int>
     */
    private function seedRows(string $modelClass, array $rows, int $sourceId, ?string $parentField = null, array $parentIds = []): array
    {
        $ids = [];

        foreach ($rows as $row) {
            $attributes = Arr::only($row, ['name', 'name_en', 'type']) + [
                'source_id' => $sourceId,
                'status' => 'active',
                'verification_status' => 'verified',
                'verified_at' => now(),
            ];

            if ($parentField !== null) {
                $parentCode = $row['parent_code'] ?? null;
                if ($parentCode !== null && ! isset($parentIds[$parentCode])) {
                    throw new LogicException('Missing TEC parent for '.$row['code']);
                }
                $attributes[$parentField] = $parentCode === null ? null : $parentIds[$parentCode];
            }

            $record = $modelClass::query()->firstOrCreate(['code' => $row['code']], $attributes);
            $ids[$row['code']] = $record->id;
        }

        return $ids;
    }
}
