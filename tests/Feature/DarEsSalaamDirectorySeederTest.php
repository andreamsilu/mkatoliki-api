<?php

namespace Tests\Feature;

use App\Models\DataSource;
use App\Models\Deanery;
use App\Models\Parish;
use Database\Seeders\DarEsSalaamDirectorySeeder;
use Database\Seeders\TecDirectorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DarEsSalaamDirectorySeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_loads_confirmed_parishes_under_their_dar_es_salaam_deaneries_idempotently(): void
    {
        $this->seed(TecDirectorySeeder::class);
        $this->seed(DarEsSalaamDirectorySeeder::class);

        $expectedCounts = [
            'DSM-STP' => 10,
            'DSM-MAK' => 10,
            'DSM-MBA' => 13,
            'DSM-UBU' => 8,
        ];

        foreach ($expectedCounts as $deaneryCode => $count) {
            $deanery = Deanery::query()->where('code', $deaneryCode)->firstOrFail();
            $this->assertSame('DSM', $deanery->diocese->code);
            $this->assertSame($count, $deanery->parishes()->where('code', 'like', 'DSM-%')->count());
        }

        $source = DataSource::query()->where('name', 'Confirmed Dar es Salaam parish-deanery mapping')->firstOrFail();
        $this->assertSame('2026-09-26', $source->version);
        $this->assertDatabaseCount('deaneries', 47);
        $this->assertDatabaseCount('parishes', 345);
        $this->assertDatabaseHas('parishes', [
            'code' => 'DSM-SINZA',
            'patron_saint' => 'Bikira Maria Mama wa Mwokozi',
            'source_id' => $source->id,
            'status' => 'active',
            'verification_status' => 'verified',
        ]);
        $this->assertDatabaseHas('parishes', [
            'code' => 'DSM-MAJIMATITU',
            'patron_saint' => 'Mt. Francisco wa Assizi',
            'source_id' => $source->id,
        ]);

        $sinza = Parish::query()->where('code', 'DSM-SINZA')->firstOrFail();
        $sinza->update(['name' => 'Reviewed Sinza Parish']);
        $originalIds = Parish::query()->where('source_id', $source->id)->orderBy('code')->pluck('id', 'code')->all();

        $this->seed(DarEsSalaamDirectorySeeder::class);

        $this->assertSame($originalIds, Parish::query()->where('source_id', $source->id)->orderBy('code')->pluck('id', 'code')->all());
        $this->assertSame('Reviewed Sinza Parish', $sinza->refresh()->name);
        $this->assertDatabaseCount('deaneries', 47);
        $this->assertDatabaseCount('parishes', 345);

        $stPeter = Deanery::query()->where('code', 'DSM-STP')->firstOrFail();
        $this->postJson('/api/v1/parishes/search', ['deanery_id' => $stPeter->id, 'per_page' => 100])
            ->assertOk()
            ->assertJsonPath('meta.total', 10)
            ->assertJsonPath('data.0.deanery_id', $stPeter->id);
    }
}
