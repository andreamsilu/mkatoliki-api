<?php

namespace Tests\Feature;

use App\Models\DataSource;
use App\Models\Deanery;
use App\Models\Diocese;
use App\Models\EcclesiasticalProvince;
use App\Models\Parish;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\TecDirectorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TecDirectorySeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_seeding_loads_and_publishes_the_documented_2020_hierarchy_without_duplicates(): void
    {
        $this->seed(DatabaseSeeder::class);
        $originalParishIds = Parish::query()->orderBy('code')->pluck('id', 'code')->all();
        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseCount('roles', 5);
        $this->assertDatabaseCount('permissions', 6);
        $this->assertDatabaseCount('data_sources', 1);
        $this->assertDatabaseCount('ecclesiastical_provinces', 7);
        $this->assertDatabaseCount('dioceses', 34);
        $this->assertDatabaseCount('deaneries', 45);
        $this->assertDatabaseCount('parishes', 304);
        $this->assertSame($originalParishIds, Parish::query()->orderBy('code')->pluck('id', 'code')->all());
        $this->assertSame(7, Diocese::query()->where('type', 'archdiocese')->count());

        $source = DataSource::query()->firstOrFail();
        $this->assertSame('2020', $source->version);
        $this->assertStringContainsString('tec.or.tz', $source->reference);
        $this->assertStringContainsString('scribd.com', $source->reference);

        foreach ([EcclesiasticalProvince::class, Diocese::class, Deanery::class, Parish::class] as $modelClass) {
            $this->assertSame(0, $modelClass::query()->where('source_id', '!=', $source->id)->count());
            $this->assertSame(0, $modelClass::query()->where('status', '!=', 'active')->count());
            $this->assertSame(0, $modelClass::query()->where('verification_status', '!=', 'verified')->count());
            $this->assertSame(0, $modelClass::query()->whereNull('verified_at')->count());
        }

        foreach ([
            ['ARU-KIJENGE', 'ARU-EAST', 'ARU', 'ARU'],
            ['KAH-BUKOMBE', 'KAH-MASUMBWE', 'KAH', 'TAB'],
            ['MSH-NARUMU', 'MSH-HAI', 'MSH', 'ARU'],
            ['MSH-REHA', 'MSH-ROMBO', 'MSH', 'ARU'],
            ['SUM-TUNDUMA', 'SUM-SOUTHERN', 'SUM', 'MBY'],
            ['TAB-NDALA', 'TAB-NZEGA', 'TAB', 'TAB'],
            ['TAN-KIFUNGILO', 'TAN-LUSHOTO', 'TAN', 'DSM'],
        ] as [$parishCode, $deaneryCode, $dioceseCode, $provinceCode]) {
            $parish = Parish::query()->where('code', $parishCode)->firstOrFail();
            $this->assertSame($deaneryCode, $parish->deanery->code);
            $this->assertSame($dioceseCode, $parish->deanery->diocese->code);
            $this->assertSame($provinceCode, $parish->deanery->diocese->province->code);
        }

        $this->assertDatabaseCount('outstations', 0);
        $this->assertDatabaseCount('zones', 0);
        $this->assertDatabaseCount('jumuiyas', 0);
        $this->assertDatabaseCount('users', 0);
        $this->assertSame(0, Parish::query()->where('code', 'like', 'DSM-%')->count());
        $this->assertDatabaseMissing('parishes', ['code' => 'MSH-KCMC-CHAPLAINCY']);
        $this->assertDatabaseMissing('parishes', ['code' => 'ARU-KIKUNDE']);

        foreach (['provinces' => 7, 'dioceses' => 34, 'deaneries' => 45, 'parishes' => 229] as $entity => $total) {
            $this->postJson('/api/v1/'.$entity.'/search')->assertOk()->assertJsonPath('meta.total', $total);
        }
    }

    public function test_reseeding_preserves_a_later_parish_transfer_and_corrections(): void
    {
        $this->seed(TecDirectorySeeder::class);
        $this->administrator();
        $parish = Parish::query()->where('code', 'ARU-KIJENGE')->firstOrFail();
        $destination = Deanery::factory()->create();
        $source = DataSource::factory()->create(['version' => '2026']);

        $this->postJson('/api/v1/admin/parishes/'.$parish->id.'/transfer', [
            'new_deanery_id' => $destination->id,
            'source_id' => $source->id,
            'effective_date' => today()->toDateString(),
            'reason' => 'Reviewed parish transfer',
        ])->assertOk();
        $this->patchJson('/api/v1/admin/parishes/'.$parish->id, ['name' => 'Reviewed Kijenge Parish'])->assertOk();
        $reviewedAttributes = $parish->refresh()->getAttributes();

        $this->seed(TecDirectorySeeder::class);

        $this->assertSame($reviewedAttributes, $parish->refresh()->getAttributes());
        $this->assertDatabaseCount('parishes', 304);
        $this->assertDatabaseCount('parish_history', 1);
        $this->assertDatabaseCount('verification_records', 0);
        $this->assertDatabaseHas('parish_history', ['parish_id' => $parish->id, 'new_deanery_id' => $destination->id]);
    }

    public function test_snapshot_retains_page_references_valid_identifiers_and_explicit_coverage_gaps(): void
    {
        $snapshot = json_decode(file_get_contents(database_path('seeders/tec-directory-2020.json')), true, flags: JSON_THROW_ON_ERROR);
        $parentCodes = [];

        foreach (['provinces', 'dioceses', 'deaneries', 'parishes'] as $entity) {
            $codes = array_column($snapshot[$entity], 'code');
            $this->assertCount(count($codes), array_unique($codes));

            foreach ($snapshot[$entity] as $row) {
                $this->assertMatchesRegularExpression('/^[A-Z0-9-]{1,64}$/', $row['code']);
                $this->assertNotEmpty($row['name']);
                $this->assertStringNotContainsString('[Link]', $row['name_en']);
                $this->assertNotEmpty($row['source_pages']);
                foreach ($row['source_pages'] as $page) {
                    $this->assertIsInt($page);
                    $this->assertGreaterThan(0, $page);
                    $this->assertLessThanOrEqual(349, $page);
                }
                if ($entity !== 'provinces' && ! ($entity === 'parishes' && array_key_exists('parent_code', $row) === false)) {
                    $this->assertContains($row['parent_code'], $parentCodes);
                }
            }
            $parentCodes = $codes;
        }

        $this->assertContains('DSM', $snapshot['coverage']['dioceses_requiring_parish_deanery_mapping']);
        $this->assertCount(28, $snapshot['coverage']['dioceses_requiring_parish_deanery_mapping']);
        $this->assertCount(12, $snapshot['coverage']['excluded_entries']);
    }
}
