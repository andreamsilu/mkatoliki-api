<?php

namespace Database\Seeders;

use App\Models\ContributionCampaign;
use App\Models\ContributionPayment;
use App\Models\DataSource;
use App\Models\Jumuiya;
use App\Models\Member;
use App\Models\MemberNotification;
use App\Models\MemberSacrament;
use App\Models\Parish;
use App\Models\User;
use App\Models\Zone;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use LogicException;

class MemberPortalDemoSeeder extends Seeder
{
    public const EMAIL = 'demo.member@mkatolik.test';

    public const PASSWORD = 'MkatolikDemo123!';

    public function run(): void
    {
        DB::transaction(function (): void {
            $source = DataSource::query()
                ->where('name', 'Confirmed Dar es Salaam parish-deanery mapping')
                ->where('version', '2026-09-26')
                ->firstOrFail();
            $parish = Parish::query()->where('code', 'DSM-SINZA')->firstOrFail();
            $zone = Zone::query()->firstOrCreate(['code' => 'DSM-SINZA-DEMO-ZONE'], [
                'parish_id' => $parish->id,
                'name' => 'Kanda ya Mfano wa Programu',
                'name_en' => 'Application Demo Zone',
                'description' => 'Data ya majaribio ya programu ya Mkatolik.',
                'status' => 'active',
                'source_id' => $source->id,
                'verification_status' => 'verified',
                'verified_at' => now(),
            ]);
            $jumuiya = Jumuiya::query()->firstOrCreate(['code' => 'DSM-SINZA-DEMO-JUMUIYA'], [
                'parish_id' => $parish->id,
                'zone_id' => $zone->id,
                'name' => 'Jumuiya ya Mfano wa Programu',
                'name_en' => 'Application Demo Jumuiya',
                'description' => 'Data ya majaribio ya Mkatolik; si rekodi rasmi ya parokia.',
                'status' => 'active',
                'source_id' => $source->id,
                'verification_status' => 'verified',
                'verified_at' => now(),
            ]);
            $member = Member::query()->firstOrCreate(['member_code' => 'DEMO-DSM-SINZA-001'], [
                'parish_id' => $parish->id,
                'zone_id' => $zone->id,
                'jumuiya_id' => $jumuiya->id,
                'first_name' => 'Mwanajumuiya',
                'last_name' => 'Mfano',
                'gender' => 'not_specified',
                'date_of_birth' => '1995-01-01',
                'phone' => '+255700000001',
                'email' => self::EMAIL,
                'status' => 'active',
            ]);

            $this->seedUser($member);
            $jumuiya->update(['leader_member_id' => $member->id]);

            $campaign = ContributionCampaign::query()->firstOrCreate([
                'parish_id' => $parish->id,
                'title' => 'Mfuko wa Mfano wa Programu',
            ], [
                'description' => 'Mchango wa majaribio unaoonyesha historia ya michango kwenye programu.',
                'target_amount' => 100000,
                'starts_on' => now()->subMonth()->toDateString(),
                'ends_on' => now()->addMonth()->toDateString(),
                'status' => 'active',
            ]);
            ContributionPayment::query()->firstOrCreate(['reference' => 'MKT-DEMO-DSM-SINZA-001'], [
                'member_id' => $member->id,
                'contribution_campaign_id' => $campaign->id,
                'amount' => 25000,
                'payment_method' => 'mobile_money',
                'status' => 'confirmed',
                'receipt_number' => 'RCT-DEMO-DSM-SINZA-001',
                'requested_at' => now()->subDay(),
                'confirmed_at' => now()->subDay(),
            ]);
            MemberSacrament::query()->firstOrCreate([
                'member_id' => $member->id,
                'name' => 'Ubatizo',
            ], [
                'received_on' => '1995-03-12',
                'place' => $parish->name,
                'status' => 'verified',
            ]);
            MemberSacrament::query()->firstOrCreate([
                'member_id' => $member->id,
                'name' => 'Kipaimara',
            ], [
                'received_on' => '2010-06-20',
                'place' => $parish->name,
                'status' => 'verified',
            ]);
            MemberNotification::query()->firstOrCreate([
                'member_id' => $member->id,
                'title' => 'Karibu kwenye akaunti ya majaribio',
            ], [
                'message' => 'Hii ni data ya majaribio ya Mkatolik; haijawakilisha rekodi rasmi ya parokia.',
                'type' => 'announcement',
                'published_at' => now()->subDay(),
            ]);
            MemberNotification::query()->firstOrCreate([
                'member_id' => $member->id,
                'title' => 'Mchango wa mfano umethibitishwa',
            ], [
                'message' => 'Mchango wa TSh 25,000 umewekwa ili kuonyesha historia ya mchango kwenye programu.',
                'type' => 'contribution',
                'published_at' => now()->subHours(2),
                'read_at' => now()->subHour(),
            ]);
        });

        $this->command?->info('Member portal demo data loaded for demo.member@mkatolik.test.');
    }

    private function seedUser(Member $member): void
    {
        $user = User::query()->where('email', self::EMAIL)->orWhere('member_id', $member->id)->first();

        if ($user !== null && ((int) $user->member_id !== (int) $member->id || $user->email !== self::EMAIL)) {
            throw new LogicException('The demo member or email is already assigned to a different user.');
        }

        if ($user === null) {
            $user = new User;
            $user->name = 'Mwanajumuiya Mfano (Demo)';
            $user->email = self::EMAIL;
            $user->password = self::PASSWORD;
            $user->email_verified_at = now();
            $user->is_active = true;
            $user->member()->associate($member);
            $user->save();
        }
    }
}
