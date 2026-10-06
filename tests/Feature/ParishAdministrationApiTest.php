<?php

namespace Tests\Feature;

use App\Models\ContributionCampaign;
use App\Models\ContributionPayment;
use App\Models\Member;
use App\Models\MemberSacrament;
use App\Models\MemberServiceRequest;
use App\Models\Parish;
use App\Models\ParishMassTime;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ParishAdministrationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_parish_administrator_manages_parish_content_modules(): void
    {
        $parish = Parish::factory()->create();
        $this->administrator('parish_admin', ['parish_id' => $parish->id]);

        $massTimeId = $this->postJson("/api/v1/admin/parishes/{$parish->id}/mass-times", [
            'day_label' => 'Sunday',
            'time_label' => '07:00 AM',
            'display_order' => 1,
            'is_published' => true,
        ])->assertCreated()
            ->assertJsonPath('data.day_label', 'Sunday')
            ->json('data.id');

        $this->postJson("/api/v1/admin/parishes/{$parish->id}/announcements", [
            'title' => 'Parish assembly',
            'summary' => 'All leaders should attend.',
            'category' => 'Parish',
            'published_at' => now()->toIso8601String(),
            'is_published' => true,
        ])->assertCreated()->assertJsonPath('data.title', 'Parish assembly');

        $this->postJson("/api/v1/admin/parishes/{$parish->id}/events", [
            'title' => 'Parish retreat',
            'starts_at' => now()->addWeek()->toIso8601String(),
            'venue' => 'Parish hall',
            'description' => 'Annual parish retreat.',
            'is_published' => true,
        ])->assertCreated()->assertJsonPath('data.venue', 'Parish hall');

        $this->postJson("/api/v1/admin/parishes/{$parish->id}/projects", [
            'title' => 'Church renovation',
            'subtitle' => 'Roof replacement',
            'progress_percentage' => 25,
            'is_published' => true,
        ])->assertCreated()->assertJsonPath('data.progress_percentage', 25);

        $this->patchJson("/api/v1/admin/mass-times/{$massTimeId}", [
            'time_label' => '07:30 AM',
        ])->assertOk()->assertJsonPath('data.time_label', '07:30 AM');
        $this->getJson("/api/v1/admin/parishes/{$parish->id}/mass-times")
            ->assertOk()
            ->assertJsonPath('data.0.id', $massTimeId)
            ->assertJsonPath('meta.total', 1);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'parish_mass_time.updated',
            'entity_id' => $massTimeId,
        ]);
    }

    public function test_parish_administrator_cannot_manage_another_parish_operations(): void
    {
        $parish = Parish::factory()->create();
        $otherParish = Parish::factory()->create();
        $otherMassTime = ParishMassTime::factory()->for($otherParish)->create();
        $this->administrator('parish_admin', ['parish_id' => $parish->id]);

        $this->getJson("/api/v1/admin/parishes/{$otherParish->id}/mass-times")->assertForbidden();
        $this->patchJson("/api/v1/admin/mass-times/{$otherMassTime->id}", [
            'time_label' => 'Changed',
        ])->assertForbidden();
        $this->assertDatabaseMissing('parish_mass_times', [
            'id' => $otherMassTime->id,
            'time_label' => 'Changed',
        ]);
    }

    public function test_parish_administrator_manages_contributions_sacraments_and_requests(): void
    {
        $parish = Parish::factory()->create();
        $member = Member::factory()->create(['parish_id' => $parish->id]);
        $this->administrator('parish_admin', ['parish_id' => $parish->id]);

        $campaignId = $this->postJson("/api/v1/admin/parishes/{$parish->id}/contribution-campaigns", [
            'title' => 'Parish development',
            'target_amount' => 1000000,
            'status' => 'active',
        ])->assertCreated()->json('data.id');
        $campaign = ContributionCampaign::findOrFail($campaignId);
        $payment = ContributionPayment::factory()
            ->for($member)
            ->for($campaign, 'campaign')
            ->create(['status' => 'pending', 'confirmed_at' => null, 'receipt_number' => null]);

        $this->patchJson("/api/v1/admin/contribution-payments/{$payment->id}", [
            'status' => 'confirmed',
        ])->assertOk()
            ->assertJsonPath('data.status', 'confirmed')
            ->assertJsonPath('data.member.id', $member->id);
        $this->assertNotNull($payment->fresh()->receipt_number);

        $sacramentId = $this->postJson("/api/v1/admin/parishes/{$parish->id}/sacraments", [
            'member_id' => $member->id,
            'name' => 'Baptism',
            'received_on' => '2020-01-15',
            'place' => $parish->name,
            'status' => 'verified',
        ])->assertCreated()->json('data.id');
        $sacrament = MemberSacrament::findOrFail($sacramentId);
        $serviceRequest = MemberServiceRequest::factory()
            ->for($member)
            ->for($sacrament, 'sacrament')
            ->create(['status' => 'pending']);

        $this->patchJson("/api/v1/admin/service-requests/{$serviceRequest->id}", [
            'status' => 'approved',
        ])->assertOk()->assertJsonPath('data.status', 'approved');
        $this->assertNotNull($serviceRequest->fresh()->reviewed_at);
    }

    public function test_parish_administrator_broadcasts_notifications_and_reads_reports(): void
    {
        $parish = Parish::factory()->create();
        Member::factory()->count(2)->create(['parish_id' => $parish->id]);
        $this->administrator('parish_admin', ['parish_id' => $parish->id]);

        $this->postJson("/api/v1/admin/parishes/{$parish->id}/notifications", [
            'title' => 'Sunday notice',
            'message' => 'Mass begins at 7:00 AM.',
            'type' => 'announcement',
            'audience' => 'all',
        ])->assertCreated()->assertJsonPath('data.sent_count', 2);
        $this->assertDatabaseCount('member_notifications', 2);

        $this->getJson("/api/v1/admin/parishes/{$parish->id}/reports/membership")
            ->assertOk()
            ->assertJsonPath('data.type', 'membership')
            ->assertJsonPath('data.data.members_total', 2);
        $this->getJson("/api/v1/admin/parishes/{$parish->id}/reports/annual")
            ->assertOk()
            ->assertJsonPath('data.type', 'annual')
            ->assertJsonPath('data.data.membership.members_total', 2);
    }
}
