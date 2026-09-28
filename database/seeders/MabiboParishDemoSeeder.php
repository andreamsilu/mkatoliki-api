<?php

namespace Database\Seeders;

use App\Models\ContributionCampaign;
use App\Models\ContributionPayment;
use App\Models\DataSource;
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
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use LogicException;

class MabiboParishDemoSeeder extends Seeder
{
    public const MEMBER_CODE = 'DEMO-DSM-MABIBO-001';

    public const EMAIL = 'demo.mabibo@mkatolik.test';

    public const PASSWORD = 'MkatolikDemo123!';

    public function run(): void
    {
        DB::transaction(function (): void {
            $source = DataSource::query()
                ->where('name', 'Confirmed Dar es Salaam parish-deanery mapping')
                ->where('version', '2026-09-26')
                ->firstOrFail();
            $parish = Parish::query()->where('code', 'DSM-MABIBO')->firstOrFail();
            [$zone, $jumuiya] = $this->seedCommunity($parish, $source);
            [$family, $member] = $this->seedMembers($parish, $zone, $jumuiya);

            $jumuiya->update(['leader_member_id' => $member->id]);
            $family->update(['head_member_id' => $member->id]);
            $this->seedUser($member);
            $this->seedPublicContent($parish);
            $this->seedMemberPortalData($parish, $member);
        });

        $this->command?->info('Complete Mabibo parish demo data loaded for demo.mabibo@mkatolik.test.');
    }

    /**
     * @return array{Zone, Jumuiya}
     */
    private function seedCommunity(Parish $parish, DataSource $source): array
    {
        $zone = Zone::query()->firstOrCreate(['code' => 'DSM-MABIBO-DEMO-ZONE'], [
            'parish_id' => $parish->id,
            'name' => 'Kanda ya Mabibo Mfano',
            'name_en' => 'Mabibo Demo Zone',
            'description' => 'Muundo wa majaribio wa programu ya Mkatolik.',
            'status' => 'active',
            'source_id' => $source->id,
            'verification_status' => 'pending',
        ]);
        $jumuiya = Jumuiya::query()->firstOrCreate(['code' => 'DSM-MABIBO-DEMO-JUMUIYA'], [
            'parish_id' => $parish->id,
            'zone_id' => $zone->id,
            'name' => 'Jumuiya ya Mabibo Mfano',
            'name_en' => 'Mabibo Demo Jumuiya',
            'description' => 'Jumuiya ya majaribio ya Mkatolik; si rekodi rasmi ya parokia.',
            'status' => 'active',
            'source_id' => $source->id,
            'verification_status' => 'pending',
        ]);

        return [$zone, $jumuiya];
    }

    /**
     * @return array{Family, Member}
     */
    private function seedMembers(Parish $parish, Zone $zone, Jumuiya $jumuiya): array
    {
        $family = Family::query()->firstOrCreate(['family_code' => 'DEMO-DSM-MABIBO-FAMILY-001'], [
            'parish_id' => $parish->id,
            'zone_id' => $zone->id,
            'jumuiya_id' => $jumuiya->id,
            'family_name' => 'Familia ya Mfano Mabibo',
            'address' => 'Data ya majaribio; si anwani rasmi.',
            'phone' => '+255700000101',
            'status' => 'active',
        ]);
        $member = Member::query()->firstOrCreate(['member_code' => self::MEMBER_CODE], [
            'parish_id' => $parish->id,
            'family_id' => $family->id,
            'zone_id' => $zone->id,
            'jumuiya_id' => $jumuiya->id,
            'first_name' => 'Mwanajumuiya',
            'last_name' => 'Mfano Mabibo',
            'gender' => 'not_specified',
            'date_of_birth' => '1992-05-15',
            'phone' => '+255700000101',
            'email' => self::EMAIL,
            'status' => 'active',
        ]);
        Member::query()->firstOrCreate(['member_code' => 'DEMO-DSM-MABIBO-002'], [
            'parish_id' => $parish->id,
            'family_id' => $family->id,
            'zone_id' => $zone->id,
            'jumuiya_id' => $jumuiya->id,
            'first_name' => 'Mshiriki',
            'last_name' => 'Mfano Mabibo',
            'gender' => 'not_specified',
            'date_of_birth' => '1996-09-22',
            'phone' => '+255700000102',
            'status' => 'active',
        ]);

        return [$family, $member];
    }

    private function seedPublicContent(Parish $parish): void
    {
        foreach ([
            ['day_label' => 'Jumapili', 'time_label' => '6:30 AM · 8:30 AM', 'display_order' => 1],
            ['day_label' => 'Jumatatu–Ijumaa', 'time_label' => '6:30 AM', 'display_order' => 2],
            ['day_label' => 'Jumamosi', 'time_label' => '6:30 AM · 5:30 PM', 'display_order' => 3],
        ] as $massTime) {
            ParishMassTime::query()->firstOrCreate([
                'parish_id' => $parish->id,
                'day_label' => $massTime['day_label'],
            ], $massTime + ['is_published' => true]);
        }
        foreach ([
            ['title' => 'Karibu Mabibo kwenye mfumo wa majaribio', 'category' => 'Mfumo', 'summary' => 'Hii ni taarifa ya majaribio ya programu ya Mkatolik; si tangazo rasmi la Parokia ya Mabibo.'],
            ['title' => 'Mkutano wa Jumuiya ya Mfano', 'category' => 'Jumuiya', 'summary' => 'Mfano wa tangazo unaoonyesha taarifa za jumuiya ndani ya programu.'],
            ['title' => 'Kumbusho la michango', 'category' => 'Huduma', 'summary' => 'Mfano wa taarifa ya mchango kwa ajili ya kujaribu sehemu ya huduma za wanajumuiya.'],
        ] as $announcement) {
            ParishAnnouncement::query()->firstOrCreate([
                'parish_id' => $parish->id,
                'title' => $announcement['title'],
            ], $announcement + ['published_at' => now(), 'is_published' => true]);
        }
        foreach ([
            ['title' => 'Siku ya Jumuiya ya Mfano', 'venue' => 'Parokia ya Mabibo', 'description' => 'Tukio la majaribio kwa ajili ya kuonyesha kalenda ya parokia.', 'starts_at' => now()->addWeek()->setTime(9, 0), 'ends_at' => now()->addWeek()->setTime(13, 0)],
            ['title' => 'Mafunzo ya huduma za kidijitali', 'venue' => 'Ukumbi wa parokia', 'description' => 'Tukio la majaribio la kuonyesha usajili na taarifa za tukio.', 'starts_at' => now()->addWeeks(2)->setTime(10, 0), 'ends_at' => now()->addWeeks(2)->setTime(12, 0)],
        ] as $event) {
            ParishEvent::query()->firstOrCreate([
                'parish_id' => $parish->id,
                'title' => $event['title'],
            ], $event + ['is_published' => true]);
        }
        foreach ([
            ['title' => 'Uboreshaji wa ukumbi wa jumuiya', 'subtitle' => 'Mradi wa majaribio wa Mkatolik', 'progress_percentage' => 45],
            ['title' => 'Vifaa vya katekesi', 'subtitle' => 'Mfano wa mradi wa huduma', 'progress_percentage' => 70],
        ] as $project) {
            ParishProject::query()->firstOrCreate([
                'parish_id' => $parish->id,
                'title' => $project['title'],
            ], $project + ['is_published' => true]);
        }
    }

    private function seedMemberPortalData(Parish $parish, Member $member): void
    {
        $campaign = ContributionCampaign::query()->firstOrCreate([
            'parish_id' => $parish->id,
            'title' => 'Mchango wa huduma za Mabibo (Mfano)',
        ], [
            'description' => 'Mchango wa majaribio wa kuonyesha historia na risiti katika programu.',
            'target_amount' => 150000,
            'starts_on' => now()->subMonth()->toDateString(),
            'ends_on' => now()->addMonth()->toDateString(),
            'status' => 'active',
        ]);
        ContributionPayment::query()->firstOrCreate(['reference' => 'MKT-DEMO-MABIBO-001'], [
            'member_id' => $member->id,
            'contribution_campaign_id' => $campaign->id,
            'amount' => 30000,
            'payment_method' => 'mobile_money',
            'status' => 'confirmed',
            'receipt_number' => 'RCT-DEMO-MABIBO-001',
            'requested_at' => now()->subDays(2),
            'confirmed_at' => now()->subDays(2),
        ]);
        foreach ([
            ['name' => 'Ubatizo', 'received_on' => '1992-07-12'],
            ['name' => 'Kipaimara', 'received_on' => '2007-10-04'],
        ] as $sacrament) {
            MemberSacrament::query()->firstOrCreate([
                'member_id' => $member->id,
                'name' => $sacrament['name'],
            ], $sacrament + ['place' => $parish->name, 'status' => 'verified']);
        }
        foreach ([
            ['title' => 'Karibu kwenye akaunti ya Mabibo ya majaribio', 'message' => 'Hii ni data ya majaribio ya Mkatolik; haijawakilisha rekodi rasmi ya Parokia ya Mabibo.', 'type' => 'announcement', 'published_at' => now()->subDay()],
            ['title' => 'Mchango wa mfano umethibitishwa', 'message' => 'Mchango wa TSh 30,000 umewekwa ili kuonyesha historia ya mchango.', 'type' => 'contribution', 'published_at' => now()->subHours(2), 'read_at' => now()->subHour()],
        ] as $notification) {
            MemberNotification::query()->firstOrCreate([
                'member_id' => $member->id,
                'title' => $notification['title'],
            ], $notification);
        }
    }

    private function seedUser(Member $member): void
    {
        $user = User::query()->where('email', self::EMAIL)->orWhere('member_id', $member->id)->first();

        if ($user !== null && ((int) $user->member_id !== (int) $member->id || $user->email !== self::EMAIL)) {
            throw new LogicException('The Mabibo demo member or email is already assigned to a different user.');
        }
        if ($user === null) {
            $user = new User;
            $user->name = 'Mwanajumuiya Mfano Mabibo (Demo)';
            $user->email = self::EMAIL;
            $user->password = self::PASSWORD;
            $user->email_verified_at = now();
            $user->is_active = true;
            $user->member()->associate($member);
            $user->save();
        }
    }
}
