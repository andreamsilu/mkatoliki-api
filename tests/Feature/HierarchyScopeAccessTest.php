<?php

namespace Tests\Feature;

use App\Models\Deanery;
use App\Models\Diocese;
use App\Models\EcclesiasticalProvince;
use App\Models\Family;
use App\Models\Jumuiya;
use App\Models\Member;
use App\Models\Parish;
use App\Models\User;
use App\Models\Zone;
use Database\Seeders\AccessControlSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class HierarchyScopeAccessTest extends TestCase
{
    use RefreshDatabase;

    public static function scopedRoles(): array
    {
        return [
            'province' => ['province_admin', 'ecclesiastical_province_id', 'provinces', null],
            'diocese' => ['diocesan_admin', 'diocese_id', 'dioceses', 'provinces'],
            'deanery' => ['deanery_admin', 'deanery_id', 'deaneries', 'dioceses'],
            'parish' => ['parish_admin', 'parish_id', 'parishes', 'deaneries'],
            'zone' => ['zone_leader', 'zone_id', 'zones', 'parishes'],
            'jumuiya' => ['jumuiya_leader', 'jumuiya_id', 'jumuiyas', 'zones'],
        ];
    }

    #[DataProvider('scopedRoles')]
    public function test_each_level_reads_only_its_own_scope_and_descendants(string $role, string $scopeField, string $entity, ?string $parentEntity): void
    {
        $own = $this->branch('OWN');
        $other = $this->branch('OTHER');
        $scopeId = $own[$entity]->id;
        $this->administrator($role, [$scopeField => $scopeId]);

        $this->postJson("/api/v1/admin/{$entity}/search")
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $scopeId);
        $this->postJson("/api/v1/admin/{$entity}/{$scopeId}")
            ->assertOk()->assertJsonPath('data.id', $scopeId);
        $this->postJson("/api/v1/admin/{$entity}/{$other[$entity]->id}")->assertNotFound();
        $this->postJson('/api/v1/admin/members/search')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $own['members']->id);
        $this->postJson("/api/v1/admin/members/{$other['members']->id}")->assertNotFound();

        if ($parentEntity) {
            $this->postJson("/api/v1/admin/{$parentEntity}/{$own[$parentEntity]->id}")->assertNotFound();
        }

        $this->postJson('/api/v1/admin/dashboard')
            ->assertOk()
            ->assertJsonPath("data.scope.{$scopeField}", $scopeId)
            ->assertJsonPath('data.counts.'.$entity, 1)
            ->assertJsonPath('data.counts.members', 1);
    }

    #[DataProvider('scopedRoles')]
    public function test_each_level_updates_its_own_record_and_creates_only_inside_its_scope(string $role, string $scopeField, string $entity, ?string $_parentEntity): void
    {
        $own = $this->branch('OWN');
        $other = $this->branch('OTHER');
        $this->administrator($role, [$scopeField => $own[$entity]->id]);

        $this->patchJson("/api/v1/admin/{$entity}/{$own[$entity]->id}", ['name' => 'Updated own scope'])
            ->assertOk()->assertJsonPath('data.name', 'Updated own scope');
        $this->patchJson("/api/v1/admin/{$entity}/{$other[$entity]->id}", ['name' => 'Forbidden change'])
            ->assertForbidden();

        [$child, $ownPayload] = $this->childPayload($role, $own, 'OWN');
        [, $otherPayload] = $this->childPayload($role, $other, 'OTHER');
        $this->postJson('/api/v1/admin/'.$child, $ownPayload)->assertCreated();
        $this->postJson('/api/v1/admin/'.$child, $otherPayload)->assertForbidden();
    }

    public static function newScopedRoles(): array
    {
        return [
            ['province_admin', 'ecclesiastical_province_id', 'provinces'],
            ['zone_leader', 'zone_id', 'zones'],
            ['jumuiya_leader', 'jumuiya_id', 'jumuiyas'],
        ];
    }

    #[DataProvider('newScopedRoles')]
    public function test_new_scoped_roles_fail_closed_without_an_assigned_organization(string $role, string $scopeField, string $entity): void
    {
        $branch = $this->branch('OWN');
        $this->administrator($role);

        $this->postJson("/api/v1/admin/{$entity}/search")->assertOk()->assertJsonCount(0, 'data');
        $this->patchJson("/api/v1/admin/{$entity}/{$branch[$entity]->id}", ['name' => 'Forbidden'])->assertForbidden();
        $this->assertNull(auth()->user()->getAttribute($scopeField));
    }

    public function test_new_level_accounts_can_be_provisioned_and_log_in_with_their_exact_scope(): void
    {
        $this->seed(AccessControlSeeder::class);
        $branch = $this->branch('OWN');
        $accounts = [
            ['province_admin', 'ecclesiastical_province_id', 'provinces'],
            ['zone_leader', 'zone_id', 'zones'],
            ['jumuiya_leader', 'jumuiya_id', 'jumuiyas'],
        ];

        foreach ($accounts as [$role, $scopeField, $entity]) {
            $email = $role.'@example.test';
            $this->artisan('core:create-admin', [
                'email' => $email,
                '--name' => $role,
                '--role' => $role,
                '--scope' => $branch[$entity]->id,
            ])->expectsQuestion('Password (at least 12 characters, with mixed case, numbers, and symbols)', 'Scoped-Test-123!')
                ->assertSuccessful();

            $user = User::where('email', $email)->firstOrFail();
            $this->assertSame($branch[$entity]->id, $user->getAttribute($scopeField));
            $this->assertTrue($user->hasPermission('directory.read'));
            $this->assertTrue($user->hasPermission('directory.write'));
            $this->assertSame($role === 'province_admin', $user->hasPermission('directory.transfer'));

            $this->postJson('/api/v1/auth/login', [
                'email' => $email,
                'password' => 'Scoped-Test-123!',
                'device_name' => 'Scope test',
            ])->assertOk()
                ->assertJsonPath('data.user.role', $role)
                ->assertJsonPath('data.user.'.$scopeField, $branch[$entity]->id);
        }
    }

    /**
     * @return array<string, EcclesiasticalProvince|Diocese|Deanery|Parish|Zone|Jumuiya|Family|Member>
     */
    private function branch(string $prefix): array
    {
        $province = EcclesiasticalProvince::factory()->create(['code' => $prefix.'-PROVINCE']);
        $diocese = Diocese::factory()->create(['ecclesiastical_province_id' => $province->id, 'code' => $prefix.'-DIOCESE']);
        $deanery = Deanery::factory()->create(['diocese_id' => $diocese->id, 'code' => $prefix.'-DEANERY']);
        $parish = Parish::factory()->create(['deanery_id' => $deanery->id, 'code' => $prefix.'-PARISH']);
        $zone = Zone::factory()->create(['parish_id' => $parish->id, 'code' => $prefix.'-ZONE']);
        $jumuiya = Jumuiya::factory()->create(['parish_id' => $parish->id, 'zone_id' => $zone->id, 'code' => $prefix.'-JUMUIYA']);
        $family = Family::factory()->create([
            'parish_id' => $parish->id,
            'zone_id' => null,
            'jumuiya_id' => $jumuiya->id,
            'family_code' => $prefix.'-FAMILY',
        ]);
        $member = Member::factory()->create([
            'parish_id' => $parish->id,
            'zone_id' => null,
            'jumuiya_id' => null,
            'family_id' => $family->id,
            'member_code' => $prefix.'-MEMBER',
        ]);

        return [
            'provinces' => $province,
            'dioceses' => $diocese,
            'deaneries' => $deanery,
            'parishes' => $parish,
            'zones' => $zone,
            'jumuiyas' => $jumuiya,
            'families' => $family,
            'members' => $member,
        ];
    }

    /**
     * @param  array<string, EcclesiasticalProvince|Diocese|Deanery|Parish|Zone|Jumuiya|Family|Member>  $branch
     * @return array{string, array<string, int|string>}
     */
    private function childPayload(string $role, array $branch, string $suffix): array
    {
        return match ($role) {
            'province_admin' => ['dioceses', [
                'ecclesiastical_province_id' => $branch['provinces']->id,
                'code' => $suffix.'-NEW-DIOCESE',
                'name' => 'New Diocese',
                'type' => 'diocese',
            ]],
            'diocesan_admin' => ['deaneries', [
                'diocese_id' => $branch['dioceses']->id,
                'code' => $suffix.'-NEW-DEANERY',
                'name' => 'New Deanery',
            ]],
            'deanery_admin' => ['parishes', [
                'deanery_id' => $branch['deaneries']->id,
                'code' => $suffix.'-NEW-PARISH',
                'name' => 'New Parish',
            ]],
            'parish_admin' => ['zones', [
                'parish_id' => $branch['parishes']->id,
                'code' => $suffix.'-NEW-ZONE',
                'name' => 'New Zone',
            ]],
            'zone_leader' => ['jumuiyas', [
                'parish_id' => $branch['parishes']->id,
                'zone_id' => $branch['zones']->id,
                'code' => $suffix.'-NEW-JUMUIYA',
                'name' => 'New Jumuiya',
            ]],
            'jumuiya_leader' => ['families', [
                'parish_id' => $branch['parishes']->id,
                'zone_id' => $branch['zones']->id,
                'jumuiya_id' => $branch['jumuiyas']->id,
                'family_code' => $suffix.'-NEW-FAMILY',
                'family_name' => 'New Family',
            ]],
        };
    }
}
