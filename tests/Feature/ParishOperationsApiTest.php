<?php

namespace Tests\Feature;

use App\Models\Family;
use App\Models\Jumuiya;
use App\Models\Member;
use App\Models\Parish;
use App\Models\Zone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ParishOperationsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_rest_endpoints_expose_the_active_parish_hierarchy_without_private_contacts(): void
    {
        $parish = Parish::factory()->create([
            'patron_saint' => 'Saint Joseph',
            'established_at' => '1998-05-01',
            'phone' => '+255700000001',
        ]);
        $zone = Zone::factory()->create([
            'parish_id' => $parish->id,
            'phone' => '+255700000002',
        ]);
        $jumuiya = Jumuiya::factory()->create([
            'parish_id' => $parish->id,
            'zone_id' => $zone->id,
            'phone' => '+255700000003',
        ]);
        $leader = Member::factory()->create(['parish_id' => $parish->id, 'zone_id' => $zone->id]);
        $zone->update(['leader_member_id' => $leader->id]);

        $this->getJson('/api/v1/parishes')
            ->assertOk()
            ->assertJsonPath('data.0.id', $parish->id)
            ->assertJsonPath('data.0.patron_saint', 'Saint Joseph')
            ->assertJsonPath('data.0.established_at', '1998-05-01')
            ->assertJsonMissingPath('data.0.phone');
        $this->getJson("/api/v1/parishes/{$parish->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $parish->id);
        $this->getJson("/api/v1/parishes/{$parish->id}/zones")
            ->assertOk()
            ->assertJsonPath('data.0.id', $zone->id)
            ->assertJsonMissingPath('data.0.phone')
            ->assertJsonMissingPath('data.0.leader_member_id');
        $this->getJson("/api/v1/zones/{$zone->id}/jumuiyas")
            ->assertOk()
            ->assertJsonPath('data.0.id', $jumuiya->id)
            ->assertJsonMissingPath('data.0.phone');
        $this->getJson("/api/v1/jumuiyas/{$jumuiya->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $jumuiya->id);
    }

    public function test_parish_administrator_can_create_the_complete_nested_phase_one_hierarchy(): void
    {
        $parish = Parish::factory()->create();
        $this->administrator('parish_admin', ['parish_id' => $parish->id]);

        $zoneId = $this->postJson("/api/v1/parishes/{$parish->id}/zones", [
            'code' => 'ZONE-001',
            'name' => 'Saint Peter Zone',
            'phone' => '+255700000010',
        ])->assertCreated()
            ->assertJsonPath('data.parish_id', $parish->id)
            ->json('data.id');

        $jumuiyaId = $this->postJson("/api/v1/zones/{$zoneId}/jumuiyas", [
            'code' => 'JUM-001',
            'name' => 'Holy Family Jumuiya',
            'email' => 'jumuiya@example.test',
        ])->assertCreated()
            ->assertJsonPath('data.parish_id', $parish->id)
            ->assertJsonPath('data.zone_id', $zoneId)
            ->json('data.id');

        $familyId = $this->postJson("/api/v1/jumuiyas/{$jumuiyaId}/families", [
            'family_code' => 'FAM-001',
            'family_name' => 'Mushi Family',
            'email' => 'family@example.test',
        ])->assertCreated()
            ->assertJsonPath('data.parish_id', $parish->id)
            ->assertJsonPath('data.zone_id', $zoneId)
            ->assertJsonPath('data.jumuiya_id', $jumuiyaId)
            ->json('data.id');

        $memberId = $this->postJson("/api/v1/families/{$familyId}/members", [
            'member_code' => 'MEM-001',
            'first_name' => 'Anna',
            'last_name' => 'Mushi',
            'gender' => 'female',
            'family_relationship' => 'daughter',
            'membership_started_at' => '2020-01-02',
        ])->assertCreated()
            ->assertJsonPath('data.parish_id', $parish->id)
            ->assertJsonPath('data.zone_id', $zoneId)
            ->assertJsonPath('data.jumuiya_id', $jumuiyaId)
            ->assertJsonPath('data.family_id', $familyId)
            ->json('data.id');

        $this->getJson("/api/v1/jumuiyas/{$jumuiyaId}/families")
            ->assertOk()->assertJsonPath('data.0.id', $familyId);
        $this->getJson("/api/v1/jumuiyas/{$jumuiyaId}/members")
            ->assertOk()->assertJsonPath('data.0.id', $memberId);
        $this->getJson("/api/v1/families/{$familyId}/members")
            ->assertOk()->assertJsonPath('data.0.id', $memberId);
        $this->getJson("/api/v1/parishes/{$parish->id}/members")
            ->assertOk()->assertJsonPath('data.0.id', $memberId);
        $this->getJson("/api/v1/families/{$familyId}")
            ->assertOk()->assertJsonPath('data.email', 'family@example.test');
        $this->getJson("/api/v1/members/{$memberId}")
            ->assertOk()->assertJsonPath('data.family_relationship', 'daughter');
    }

    public function test_nested_parent_always_determines_the_created_records_hierarchy(): void
    {
        $parish = Parish::factory()->create();
        $otherParish = Parish::factory()->create();
        $this->administrator('parish_admin', ['parish_id' => $parish->id]);

        $this->postJson("/api/v1/parishes/{$parish->id}/zones", [
            'parish_id' => $otherParish->id,
            'code' => 'ROUTE-SCOPE',
            'name' => 'Route Scoped Zone',
        ])->assertCreated()->assertJsonPath('data.parish_id', $parish->id);

        $this->postJson("/api/v1/parishes/{$otherParish->id}/zones", [
            'code' => 'FOREIGN-SCOPE',
            'name' => 'Foreign Zone',
        ])->assertNotFound();

        $this->getJson("/api/v1/parishes/{$otherParish->id}/members")->assertNotFound();
    }

    public function test_leaders_must_belong_to_the_organization_they_lead(): void
    {
        $zone = Zone::factory()->create();
        $member = Member::factory()->create([
            'parish_id' => $zone->parish_id,
            'zone_id' => $zone->id,
        ]);
        $otherMember = Member::factory()->create(['parish_id' => $zone->parish_id]);
        $otherZone = Zone::factory()->create(['parish_id' => $zone->parish_id]);
        $this->administrator('parish_admin', ['parish_id' => $zone->parish_id]);

        $this->patchJson("/api/v1/zones/{$zone->id}", ['leader_member_id' => $member->id])
            ->assertOk()->assertJsonPath('data.leader_member_id', $member->id);
        $this->patchJson("/api/v1/zones/{$zone->id}", ['leader_member_id' => $otherMember->id])
            ->assertUnprocessable()
            ->assertJsonPath('error.details.leader_member_id.0', 'The selected leader must be a member of this organization.');
        $this->patchJson("/api/v1/members/{$member->id}", ['zone_id' => $otherZone->id])
            ->assertUnprocessable()
            ->assertJsonPath('error.details.zone_id.0', 'A designated leader cannot be moved out of the organization they lead.');
    }

    public function test_delete_soft_deletes_empty_records_but_rejects_records_with_dependents(): void
    {
        $parish = Parish::factory()->create();
        $family = Family::factory()->create(['parish_id' => $parish->id]);
        $familyWithMember = Family::factory()->create(['parish_id' => $parish->id]);
        Member::factory()->create(['parish_id' => $parish->id, 'family_id' => $familyWithMember->id]);
        $this->administrator('parish_admin', ['parish_id' => $parish->id]);

        $this->deleteJson("/api/v1/families/{$family->id}")->assertNoContent();
        $this->assertSoftDeleted('families', ['id' => $family->id]);
        $this->assertDatabaseHas('audit_logs', [
            'entity_type' => 'families',
            'entity_id' => $family->id,
            'action' => 'deleted',
        ]);

        $this->deleteJson("/api/v1/families/{$familyWithMember->id}")
            ->assertUnprocessable()
            ->assertJsonPath('error.details.families.0', 'This record cannot be deleted while dependent records exist.');
        $this->assertNotSoftDeleted('families', ['id' => $familyWithMember->id]);
    }

    public function test_parish_creation_and_operational_fields_use_the_canonical_routes(): void
    {
        $this->administrator();

        $parishId = $this->postJson('/api/v1/parishes', [
            'code' => 'PARISH-OPS',
            'name' => 'Operations Parish',
            'patron_saint' => 'Saint Monica',
            'established_at' => '2001-08-27',
        ])->assertCreated()
            ->assertJsonPath('data.patron_saint', 'Saint Monica')
            ->assertJsonPath('data.established_at', '2001-08-27')
            ->json('data.id');

        $this->patchJson("/api/v1/parishes/{$parishId}", [
            'phone' => '+255700000099',
            'status' => 'inactive',
        ])->assertOk()
            ->assertJsonPath('data.phone', '+255700000099')
            ->assertJsonPath('data.status', 'inactive');
    }
}
