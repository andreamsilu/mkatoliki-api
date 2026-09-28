<?php

namespace Tests\Feature;

use App\Models\ContributionPayment;
use App\Models\Family;
use App\Models\Jumuiya;
use App\Models\Member;
use App\Models\MemberNotification;
use App\Models\MemberSacrament;
use App\Models\Parish;
use App\Models\ParishAnnouncement;
use App\Models\ParishEvent;
use App\Models\ParishMassTime;
use App\Models\ParishProject;
use App\Models\User;
use App\Models\Zone;
use Database\Seeders\DarEsSalaamDirectorySeeder;
use Database\Seeders\MabiboParishDemoSeeder;
use Database\Seeders\TecDirectorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MabiboParishDemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_seeds_complete_idempotent_demo_data_for_mabibo(): void
    {
        $this->seed([TecDirectorySeeder::class, DarEsSalaamDirectorySeeder::class, MabiboParishDemoSeeder::class]);

        $parish = Parish::query()->where('code', 'DSM-MABIBO')->firstOrFail();
        $member = Member::query()->where('member_code', MabiboParishDemoSeeder::MEMBER_CODE)->firstOrFail();
        $user = User::query()->where('member_id', $member->id)->firstOrFail();

        $this->assertSame($parish->id, $member->parish_id);
        $this->assertSame(1, Zone::query()->where('parish_id', $parish->id)->where('code', 'DSM-MABIBO-DEMO-ZONE')->count());
        $this->assertSame(1, Jumuiya::query()->where('parish_id', $parish->id)->where('code', 'DSM-MABIBO-DEMO-JUMUIYA')->count());
        $this->assertSame(1, Family::query()->where('parish_id', $parish->id)->where('family_code', 'DEMO-DSM-MABIBO-FAMILY-001')->count());
        $this->assertTrue(Hash::check(MabiboParishDemoSeeder::PASSWORD, $user->password));
        $this->assertSame(3, ParishMassTime::query()->where('parish_id', $parish->id)->count());
        $this->assertSame(3, ParishAnnouncement::query()->where('parish_id', $parish->id)->count());
        $this->assertSame(2, ParishEvent::query()->where('parish_id', $parish->id)->count());
        $this->assertSame(2, ParishProject::query()->where('parish_id', $parish->id)->count());
        $this->assertSame(1, ContributionPayment::query()->where('member_id', $member->id)->count());
        $this->assertSame(2, MemberSacrament::query()->where('member_id', $member->id)->count());
        $this->assertSame(2, MemberNotification::query()->where('member_id', $member->id)->count());

        $this->getJson("/api/v1/parishes/{$parish->id}/content")
            ->assertOk()
            ->assertJsonCount(3, 'data.mass_times')
            ->assertJsonCount(3, 'data.announcements')
            ->assertJsonCount(2, 'data.events')
            ->assertJsonCount(2, 'data.projects');
        $this->postJson('/api/v1/member/auth/login', [
            'identity' => MabiboParishDemoSeeder::EMAIL,
            'password' => MabiboParishDemoSeeder::PASSWORD,
            'device_name' => 'test-phone',
        ])->assertOk()
            ->assertJsonPath('data.member.member_code', $member->member_code);

        $this->seed(MabiboParishDemoSeeder::class);

        $this->assertSame(1, Member::query()->where('member_code', MabiboParishDemoSeeder::MEMBER_CODE)->count());
        $this->assertSame(3, ParishMassTime::query()->where('parish_id', $parish->id)->count());
        $this->assertSame(3, ParishAnnouncement::query()->where('parish_id', $parish->id)->count());
        $this->assertSame(2, ParishEvent::query()->where('parish_id', $parish->id)->count());
        $this->assertSame(2, ParishProject::query()->where('parish_id', $parish->id)->count());
    }
}
