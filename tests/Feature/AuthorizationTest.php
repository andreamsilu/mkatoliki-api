<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\DataSource;
use App\Models\Deanery;
use App\Models\Family;
use App\Models\Jumuiya;
use App\Models\Member;
use App\Models\Outstation;
use App\Models\Parish;
use App\Models\Zone;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public static function scopedRoles(): array
    {
        return [['parish_admin', 'parish_id'], ['deanery_admin', 'deanery_id'], ['diocesan_admin', 'diocese_id']];
    }

    #[DataProvider('scopedRoles')]
    public function test_scoped_administrators_can_only_read_and_modify_their_own_members(string $role, string $scope): void
    {
        $own = Member::factory()->create();
        $other = Member::factory()->create();
        $scopeId = match ($scope) {
            'parish_id' => $own->parish_id, 'deanery_id' => $own->parish->deanery_id, 'diocese_id' => $own->parish->deanery->diocese_id
        };
        $this->administrator($role, [$scope => $scopeId]);
        $this->getJson('/api/v1/members')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $own->id);
        $this->getJson("/api/v1/members/{$other->id}")->assertNotFound();
        $this->patchJson("/api/v1/members/{$other->id}", ['first_name' => 'Changed'])->assertForbidden();
        $this->patchJson("/api/v1/members/{$own->id}", ['first_name' => 'Allowed'])->assertOk();
        $this->patchJson("/api/v1/members/{$own->id}", ['parish_id' => $other->parish_id])->assertForbidden();
        $this->assertDatabaseHas('members', ['id' => $own->id, 'parish_id' => $own->parish_id]);
        $this->assertDatabaseHas('audit_logs', ['entity_id' => $own->id, 'action' => 'updated']);
    }

    public function test_missing_role_scope_fails_closed_and_read_only_tokens_cannot_write(): void
    {
        $member = Member::factory()->create();
        $this->administrator('parish_admin');
        $this->getJson('/api/v1/members')->assertOk()->assertJsonCount(0, 'data');
        $this->patchJson("/api/v1/members/{$member->id}", ['first_name' => 'Blocked'])->assertForbidden();
        $this->administrator('super_admin', abilities: ['directory:read']);
        $this->getJson('/api/v1/members')->assertOk()->assertJsonCount(1, 'data');
        $this->patchJson("/api/v1/members/{$member->id}", ['first_name' => 'Blocked'])->assertForbidden();
    }

    public function test_parish_only_registration_is_supported_and_personal_audits_are_redacted(): void
    {
        $parish = Parish::factory()->create();
        $this->administrator('parish_admin', ['parish_id' => $parish->id]);
        $this->postJson('/api/v1/families', ['parish_id' => $parish->id, 'family_code' => 'F-001', 'family_name' => 'Private Family', 'phone' => '+255712345678'])->assertCreated()->assertJsonPath('data.jumuiya_id', null);
        $this->postJson('/api/v1/members', ['parish_id' => $parish->id, 'member_code' => 'M-001', 'first_name' => 'PrivateFirst', 'last_name' => 'PrivateLast', 'gender' => 'female'])->assertCreated()->assertJsonPath('data.family_id', null);
        $audit = AuditLog::where('entity_type', 'members')->firstOrFail();
        $this->assertSame('[REDACTED]', $audit->new_values['first_name']);
        $this->assertStringNotContainsString('PrivateFirst', $audit->toJson());
        $this->getJson('/api/v1/admin/audit-logs')->assertForbidden();
        $this->deleteJson('/api/v1/members/1')->assertStatus(405);
    }

    public function test_cross_parish_and_inconsistent_same_parish_ancestors_are_rejected(): void
    {
        $parish = Parish::factory()->create();
        $ownZone = Zone::factory()->create(['parish_id' => $parish->id]);
        $otherZone = Zone::factory()->create();
        $jumuiya = Jumuiya::factory()->create(['parish_id' => $parish->id, 'zone_id' => $ownZone->id]);
        $differentZone = Zone::factory()->create(['parish_id' => $parish->id]);
        $this->administrator();
        $base = ['parish_id' => $parish->id, 'family_code' => 'F-TEST', 'family_name' => 'Family'];
        $this->postJson('/api/v1/families', $base + ['zone_id' => $otherZone->id])->assertUnprocessable();
        $this->postJson('/api/v1/families', $base + ['zone_id' => $differentZone->id, 'jumuiya_id' => $jumuiya->id])->assertUnprocessable();
        $this->assertDatabaseCount('families', 0);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_kigango_is_optional_and_cannot_belong_to_another_parish(): void
    {
        $parish = Parish::factory()->create();
        $outstation = Outstation::factory()->create();
        $this->administrator();
        $base = ['parish_id' => $parish->id, 'code' => 'Z-001', 'name' => 'Kanda A'];
        $this->postJson('/api/v1/admin/zones', $base + ['outstation_id' => $outstation->id])->assertUnprocessable();
        $this->postJson('/api/v1/admin/zones', $base)->assertCreated()->assertJsonPath('data.outstation_id', null);
    }

    public function test_parish_can_be_entered_before_deanery_assignment_and_later_assigned(): void
    {
        $this->administrator('super_admin');
        $deanery = Deanery::factory()->create();

        $response = $this->postJson('/api/v1/admin/parishes', [
            'code' => 'P-UNASSIGNED',
            'name' => 'Unassigned Parish',
        ]);

        $response->assertCreated()->assertJsonPath('data.deanery_id', null);
        $parish = Parish::where('code', 'P-UNASSIGNED')->firstOrFail();

        $source = DataSource::factory()->create();
        $this->postJson('/api/v1/admin/parishes/'.$parish->id.'/transfer', [
            'new_deanery_id' => $deanery->id,
            'effective_date' => now()->toDateString(),
            'reason' => 'Deanery assignment confirmed',
            'source_id' => $source->id,
        ])->assertOk();

        $this->assertDatabaseHas('parishes', ['id' => $parish->id, 'deanery_id' => $deanery->id]);
        $this->assertDatabaseHas('parish_history', ['parish_id' => $parish->id, 'old_deanery_id' => null, 'old_diocese_id' => null, 'new_deanery_id' => $deanery->id]);
    }

    public function test_reparenting_a_zone_with_children_is_rejected(): void
    {
        $jumuiya = Jumuiya::factory()->create();
        $other = Parish::factory()->create();
        $this->administrator();
        $this->patchJson("/api/v1/admin/zones/{$jumuiya->zone_id}", ['parish_id' => $other->id])->assertUnprocessable();
        $this->assertDatabaseHas('zones', ['id' => $jumuiya->zone_id, 'parish_id' => $jumuiya->parish_id]);
    }

    public function test_database_enforces_same_parish_foreign_keys_without_api_validation(): void
    {
        $zone = Zone::factory()->create();
        $parish = Parish::factory()->create();
        $this->expectException(QueryException::class);
        Family::factory()->create(['parish_id' => $parish->id, 'zone_id' => $zone->id]);
    }

    public function test_invalid_codes_duplicate_codes_dates_and_verification_mass_assignment_are_rejected(): void
    {
        $parish = Parish::factory()->create();
        $this->administrator();
        $this->postJson('/api/v1/admin/parishes', ['deanery_id' => $parish->deanery_id, 'name' => 'Parish', 'code' => $parish->code])->assertUnprocessable();
        $this->patchJson("/api/v1/admin/parishes/{$parish->id}", ['code' => 'invalid code'])->assertUnprocessable();
        $this->patchJson("/api/v1/admin/parishes/{$parish->id}", ['verification_status' => 'verified'])->assertUnprocessable();
        $this->patchJson("/api/v1/admin/parishes/{$parish->id}", ['latitude' => 91])->assertUnprocessable();
        $member = Member::factory()->create();
        $this->patchJson("/api/v1/members/{$member->id}", ['date_of_birth' => now()->addDay()->toDateString()])->assertUnprocessable();
    }
}
