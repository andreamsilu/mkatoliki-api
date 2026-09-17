<?php

namespace Tests\Feature;

use App\Models\DataSource;
use App\Models\Deanery;
use App\Models\EcclesiasticalProvince;
use App\Models\ImportBatch;
use App\Models\Parish;
use App\Models\ParishHistory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GovernanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_records_publish_immediately_and_edits_remain_public(): void
    {
        $this->administrator();
        $id = $this->postJson('/api/v1/admin/provinces', ['code' => 'ARU', 'name' => 'Jimbo Kuu la Arusha'])
            ->assertCreated()->assertJsonMissingPath('data.verification_status')->json('data.id');
        $this->postJson('/api/v1/provinces/search')->assertOk()->assertJsonCount(1, 'data');
        $this->patchJson("/api/v1/admin/provinces/$id", ['name' => 'Updated name'])->assertOk();
        $this->postJson('/api/v1/provinces/search')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Updated name');
        $this->postJson("/api/v1/admin/provinces/$id/verify", [])->assertNotFound();
        $this->assertDatabaseCount('verification_records', 0);
    }

    public function test_parish_transfer_preserves_history_and_checks_both_scopes(): void
    {
        $parish = Parish::factory()->create();
        $destination = Deanery::factory()->create();
        $source = DataSource::factory()->create();
        $this->administrator('diocesan_admin', ['diocese_id' => $parish->deanery->diocese_id]);
        $payload = ['new_deanery_id' => $destination->id, 'source_id' => $source->id, 'effective_date' => today()->toDateString(), 'reason' => 'Official transfer'];
        $this->postJson("/api/v1/admin/parishes/{$parish->id}/transfer", $payload)->assertForbidden();
        $this->assertDatabaseCount('parish_history', 0);
        $this->administrator();
        $this->patchJson("/api/v1/admin/parishes/{$parish->id}", ['deanery_id' => $destination->id])->assertUnprocessable();
        $this->postJson("/api/v1/admin/parishes/{$parish->id}/transfer", $payload)->assertOk()->assertJsonPath('data.deanery_id', $destination->id);
        $history = ParishHistory::firstOrFail();
        $this->assertSame($parish->deanery_id, $history->old_deanery_id);
        $this->assertSame($destination->diocese_id, $history->new_diocese_id);
        $this->assertDatabaseHas('audit_logs', ['entity_id' => $parish->id, 'action' => 'transferred']);
        $this->postJson("/api/v1/admin/parishes/{$parish->id}/history")->assertOk()->assertJsonCount(1, 'data');
        $this->postJson("/api/v1/admin/parishes/{$parish->id}/transfer", $payload)->assertUnprocessable();
    }

    public function test_reviewed_import_is_published_when_committed(): void
    {
        $this->administrator();
        $source = DataSource::factory()->create();
        $payload = ['entity_type' => 'provinces', 'source_id' => $source->id, 'rows' => [['code' => ' aru ', 'name' => ' Jimbo Kuu la Arusha ']]];
        $batch = $this->postJson('/api/v1/admin/imports', $payload)->assertCreated()->assertJsonPath('data.status', 'staged')->json('data.id');
        $this->assertDatabaseCount('ecclesiastical_provinces', 0);
        $this->postJson('/api/v1/admin/imports', $payload)->assertCreated()->assertJsonPath('data.id', $batch);
        $this->postJson("/api/v1/admin/imports/$batch/commit", [])->assertUnprocessable();
        $this->postJson("/api/v1/admin/imports/$batch/commit", ['reviewed' => true])->assertOk()->assertJsonPath('data.status', 'committed');
        $this->postJson("/api/v1/admin/imports/$batch/commit", ['reviewed' => true])->assertOk();
        $this->assertDatabaseCount('ecclesiastical_provinces', 1);
        $this->assertDatabaseCount('import_batches', 1);
        $province = EcclesiasticalProvince::firstOrFail();
        $this->assertSame('ARU', $province->code);
        $this->assertSame($source->id, $province->source_id);
        $this->assertSame('active', $province->status);
        $this->postJson('/api/v1/provinces/search')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_import_reports_duplicate_codes_missing_names_and_missing_parents(): void
    {
        $this->administrator();
        $source = DataSource::factory()->create();
        $payload = ['entity_type' => 'dioceses', 'source_id' => $source->id, 'rows' => [
            ['code' => 'INVALID CODE', 'name' => 'Diocese', 'type' => 'diocese', 'ecclesiastical_province_id' => 999],
            ['code' => 'MISSING-NAME', 'type' => 'diocese'],
        ]];
        $this->postJson('/api/v1/admin/imports', $payload)->assertCreated()->assertJsonPath('data.status', 'invalid')->assertJsonPath('data.report.invalid', 2);
        $payload = ['entity_type' => 'provinces', 'source_id' => $source->id, 'rows' => [['code' => 'ARU', 'name' => 'A'], ['code' => 'ARU', 'name' => 'B']]];
        $batch = $this->postJson('/api/v1/admin/imports', $payload)->assertCreated()->assertJsonPath('data.report.invalid', 1)->json('data.id');
        $this->postJson("/api/v1/admin/imports/$batch/commit", ['reviewed' => true])->assertConflict();
        $this->assertDatabaseCount('ecclesiastical_provinces', 0);
    }

    public function test_import_revalidates_staged_rows_against_current_hierarchy(): void
    {
        $this->administrator();
        $parish = Parish::factory()->create();
        $source = DataSource::factory()->create();
        $payload = ['entity_type' => 'outstations', 'source_id' => $source->id, 'rows' => [['code' => 'OUT', 'name' => 'Outstation', 'parish_id' => $parish->id]]];
        $batch = $this->postJson('/api/v1/admin/imports', $payload)->assertCreated()->json('data.id');
        $parish->delete();
        $this->postJson("/api/v1/admin/imports/$batch/commit", ['reviewed' => true])->assertUnprocessable();
        $this->assertDatabaseCount('outstations', 0);
        $this->assertSame('staged', ImportBatch::findOrFail($batch)->status);
    }

    public function test_parish_administrators_cannot_import_or_manage_provenance(): void
    {
        $parish = Parish::factory()->create();
        $this->administrator('parish_admin', ['parish_id' => $parish->id]);
        $this->postJson('/api/v1/admin/imports/search')->assertForbidden();
        $this->postJson('/api/v1/admin/imports', [])->assertForbidden();
        $this->postJson('/api/v1/admin/data-sources', [])->assertForbidden();
    }
}
