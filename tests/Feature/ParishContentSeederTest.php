<?php

namespace Tests\Feature;

use App\Models\Parish;
use App\Models\ParishAnnouncement;
use Database\Seeders\DarEsSalaamDirectorySeeder;
use Database\Seeders\ParishContentSeeder;
use Database\Seeders\TecDirectorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ParishContentSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_seeds_public_welcome_content_for_each_confirmed_dar_es_salaam_parish(): void
    {
        $this->seed([TecDirectorySeeder::class, DarEsSalaamDirectorySeeder::class, ParishContentSeeder::class]);

        $this->assertDatabaseCount('parish_announcements', 41);
        $sinza = Parish::query()->where('code', 'DSM-SINZA')->firstOrFail();

        $this->getJson("/api/v1/parishes/{$sinza->id}/content")
            ->assertOk()
            ->assertJsonPath('data.name', 'Parokia ya Sinza')
            ->assertJsonPath('data.announcements.0.title', 'Karibu katika huduma za kidijitali')
            ->assertJsonPath('data.announcements.0.category', 'Mfumo');
    }

    public function test_reseeding_content_is_idempotent_and_preserves_parish_edits(): void
    {
        $this->seed([TecDirectorySeeder::class, DarEsSalaamDirectorySeeder::class, ParishContentSeeder::class]);
        $announcement = ParishAnnouncement::query()->where('category', 'Mfumo')->firstOrFail();
        $announcement->update(['summary' => 'Taarifa iliyohaririwa na parokia.']);

        $this->seed(ParishContentSeeder::class);

        $this->assertDatabaseCount('parish_announcements', 41);
        $this->assertSame('Taarifa iliyohaririwa na parokia.', $announcement->fresh()->summary);
    }
}
