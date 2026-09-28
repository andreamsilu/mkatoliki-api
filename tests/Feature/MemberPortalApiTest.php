<?php

namespace Tests\Feature;

use App\Models\ContributionCampaign;
use App\Models\ContributionPayment;
use App\Models\Member;
use App\Models\MemberNotification;
use App\Models\MemberSacrament;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MemberPortalApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_can_read_only_their_dashboard_and_mark_a_notification_read(): void
    {
        [$member, $user] = $this->memberAccount();
        $campaign = ContributionCampaign::factory()->create(['parish_id' => $member->parish_id, 'target_amount' => 100000, 'status' => 'active']);
        ContributionPayment::factory()->for($member)->for($campaign, 'campaign')->create(['amount' => 25000, 'status' => 'confirmed']);
        $sacrament = MemberSacrament::factory()->for($member)->create(['name' => 'Baptism']);
        $notification = MemberNotification::factory()->for($member)->create(['title' => 'Parish update', 'read_at' => null]);
        $otherNotification = MemberNotification::factory()->create();
        $token = $user->createToken('phone', ['member:read'], now()->addHour())->plainTextToken;

        $this->withToken($token)->getJson('/api/v1/member/dashboard')
            ->assertOk()
            ->assertJsonPath('data.profile.member_code', $member->member_code)
            ->assertJsonPath('data.contributions.0.id', $campaign->id)
            ->assertJsonPath('data.contributions.0.paid_amount', 25000)
            ->assertJsonPath('data.sacraments.0.id', $sacrament->id)
            ->assertJsonPath('data.notifications.0.id', $notification->id);

        $this->withToken($token)->patchJson("/api/v1/member/notifications/{$notification->id}/read")
            ->assertOk()
            ->assertJsonPath('data.id', $notification->id);
        $this->assertNotNull($notification->fresh()->read_at);
        $this->withToken($token)->patchJson("/api/v1/member/notifications/{$otherNotification->id}/read")->assertNotFound();
    }

    public function test_member_can_update_profile_and_password(): void
    {
        [$member, $user] = $this->memberAccount();
        $token = $user->createToken('phone', ['member:read'], now()->addHour())->plainTextToken;

        $this->withToken($token)->patchJson('/api/v1/member/profile', [
            'first_name' => 'Neema',
            'phone' => '+255712000000',
            'email' => 'neema@example.test',
        ])->assertOk()
            ->assertJsonPath('data.name', 'Neema Mrema')
            ->assertJsonPath('data.email', 'neema@example.test');
        $this->assertDatabaseHas('members', ['id' => $member->id, 'first_name' => 'Neema', 'phone' => '+255712000000']);
        $this->assertDatabaseHas('users', ['id' => $user->id, 'email' => 'neema@example.test']);

        $this->withToken($token)->putJson('/api/v1/member/password', [
            'current_password' => 'secure-password',
            'password' => 'updated-password',
            'password_confirmation' => 'updated-password',
        ])->assertOk();
        $this->postJson('/api/v1/member/auth/login', [
            'identity' => 'neema@example.test',
            'password' => 'updated-password',
            'device_name' => 'test',
        ])->assertOk();
    }

    public function test_member_can_create_pending_payment_and_sacrament_service_requests_only_for_their_records(): void
    {
        [$member, $user] = $this->memberAccount();
        $campaign = ContributionCampaign::factory()->create(['parish_id' => $member->parish_id, 'status' => 'active']);
        $otherCampaign = ContributionCampaign::factory()->create();
        $sacrament = MemberSacrament::factory()->for($member)->create();
        $otherSacrament = MemberSacrament::factory()->create();
        $token = $user->createToken('phone', ['member:read'], now()->addHour())->plainTextToken;

        $this->withToken($token)->postJson("/api/v1/member/contributions/{$campaign->id}/payment-requests", [
            'amount' => 10000,
            'payment_method' => 'mobile_money',
        ])->assertCreated()
            ->assertJsonPath('data.status', 'pending');
        $this->assertDatabaseHas('contribution_payments', ['member_id' => $member->id, 'contribution_campaign_id' => $campaign->id, 'status' => 'pending']);
        $this->withToken($token)->postJson("/api/v1/member/contributions/{$otherCampaign->id}/payment-requests", [
            'amount' => 10000,
            'payment_method' => 'mobile_money',
        ])->assertNotFound();

        $this->withToken($token)->postJson("/api/v1/member/sacraments/{$sacrament->id}/requests", [
            'type' => 'certificate',
        ])->assertCreated()
            ->assertJsonPath('data.status', 'pending');
        $this->withToken($token)->postJson("/api/v1/member/sacraments/{$otherSacrament->id}/requests", [
            'type' => 'certificate',
        ])->assertNotFound();
    }

    /**
     * @return array{Member, User}
     */
    private function memberAccount(): array
    {
        $member = Member::factory()->create([
            'member_code' => 'MEM-PORTAL-001',
            'first_name' => 'Asha',
            'last_name' => 'Mrema',
            'email' => 'asha@example.test',
        ]);
        $user = User::factory()->create(['email' => 'asha.login@example.test', 'password' => 'secure-password']);
        $user->member()->associate($member);
        $user->save();

        return [$member, $user];
    }
}
