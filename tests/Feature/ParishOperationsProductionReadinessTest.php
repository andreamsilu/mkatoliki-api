<?php

namespace Tests\Feature;

use App\Models\Member;
use App\Models\MemberNotification;
use App\Models\Parish;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ParishOperationsProductionReadinessTest extends TestCase
{
    use RefreshDatabase;

    public function test_optional_parish_content_fields_use_database_defaults_when_omitted(): void
    {
        $parish = Parish::factory()->create();
        $this->administrator('parish_admin', ['parish_id' => $parish->id]);

        $this->postJson("/api/v1/admin/parishes/{$parish->id}/mass-times", [
            'day_label' => 'Sunday',
            'time_label' => '07:00 AM',
        ])->assertCreated()
            ->assertJsonPath('data.display_order', 0);

        $this->postJson("/api/v1/admin/parishes/{$parish->id}/announcements", [
            'title' => 'Parish notice',
            'summary' => 'A production notice.',
        ])->assertCreated()
            ->assertJsonPath('data.category', 'Parish');

        $this->postJson("/api/v1/admin/parishes/{$parish->id}/projects", [
            'title' => 'Parish project',
        ])->assertCreated()
            ->assertJsonPath('data.progress_percentage', 0);
    }

    public function test_scheduled_notifications_are_hidden_from_members_until_publication(): void
    {
        $member = Member::factory()->create();
        $user = User::factory()->create();
        $user->member()->associate($member);
        $user->save();
        $published = MemberNotification::factory()->for($member)->create([
            'published_at' => now()->subMinute(),
        ]);
        $scheduled = MemberNotification::factory()->for($member)->create([
            'published_at' => now()->addDay(),
        ]);
        $token = $user->createToken('phone', ['member:read'], now()->addHour())->plainTextToken;

        $this->withToken($token)->getJson('/api/v1/member/dashboard')
            ->assertOk()
            ->assertJsonCount(1, 'data.notifications')
            ->assertJsonPath('data.notifications.0.id', $published->id);

        $this->withToken($token)
            ->patchJson("/api/v1/member/notifications/{$scheduled->id}/read")
            ->assertNotFound();
    }
}
