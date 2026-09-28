<?php

namespace Tests\Feature;

use App\Models\ContributionPayment;
use App\Models\Member;
use App\Models\MemberNotification;
use App\Models\MemberSacrament;
use App\Models\User;
use Database\Seeders\DarEsSalaamDirectorySeeder;
use Database\Seeders\MemberPortalDemoSeeder;
use Database\Seeders\TecDirectorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MemberPortalDemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_seeds_an_idempotent_demo_member_portal_dataset(): void
    {
        $this->seed([TecDirectorySeeder::class, DarEsSalaamDirectorySeeder::class, MemberPortalDemoSeeder::class]);

        $member = Member::query()->where('member_code', 'DEMO-DSM-SINZA-001')->firstOrFail();
        $user = User::query()->where('member_id', $member->id)->firstOrFail();

        $this->assertSame('DSM-SINZA', $member->parish->code);
        $this->assertSame('DSM-SINZA-DEMO-ZONE', $member->zone->code);
        $this->assertSame('DSM-SINZA-DEMO-JUMUIYA', $member->jumuiya->code);
        $this->assertSame(MemberPortalDemoSeeder::EMAIL, $user->email);
        $this->assertTrue(Hash::check(MemberPortalDemoSeeder::PASSWORD, $user->password));
        $this->assertSame(1, ContributionPayment::query()->where('member_id', $member->id)->count());
        $this->assertSame(2, MemberSacrament::query()->where('member_id', $member->id)->count());
        $this->assertSame(2, MemberNotification::query()->where('member_id', $member->id)->count());

        $this->seed(MemberPortalDemoSeeder::class);

        $this->assertSame(1, Member::query()->where('member_code', 'DEMO-DSM-SINZA-001')->count());
        $this->assertSame(1, ContributionPayment::query()->where('member_id', $member->id)->count());
        $this->assertSame(2, MemberSacrament::query()->where('member_id', $member->id)->count());
        $this->assertSame(2, MemberNotification::query()->where('member_id', $member->id)->count());

        $this->postJson('/api/v1/member/auth/login', [
            'identity' => MemberPortalDemoSeeder::EMAIL,
            'password' => MemberPortalDemoSeeder::PASSWORD,
            'device_name' => 'test-phone',
        ])->assertOk()
            ->assertJsonPath('data.member.member_code', $member->member_code);
    }
}
