<?php

namespace Tests\Feature;

use App\Models\Association;
use App\Models\Choir;
use App\Models\DirectoryEntity;
use App\Models\Family;
use App\Models\Jumuiya;
use App\Models\Member;
use App\Models\Ministry;
use App\Models\Outstation;
use App\Models\Parish;
use App\Models\User;
use App\Models\Zone;
use Database\Seeders\AccessControlSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ParishDataEntryTest extends TestCase
{
    use RefreshDatabase;

    public static function parishEntities(): array
    {
        return [
            'vigango' => ['outstations', Outstation::class, ['code' => 'P-OUT', 'name' => 'Kigango A'], 'name'],
            'kanda' => ['zones', Zone::class, ['code' => 'P-ZONE', 'name' => 'Kanda A'], 'name'],
            'jumuiya' => ['jumuiyas', Jumuiya::class, ['code' => 'P-JUM', 'name' => 'Jumuiya A'], 'name'],
            'families' => ['families', Family::class, ['family_code' => 'P-FAM', 'family_name' => 'Familia A'], 'family_name'],
            'members' => ['members', Member::class, ['member_code' => 'P-MEM', 'first_name' => 'Anna', 'last_name' => 'Example', 'gender' => 'female'], 'first_name'],
            'associations' => ['associations', Association::class, ['code' => 'P-ASSOC', 'name' => 'Association A'], 'name'],
            'choirs' => ['choirs', Choir::class, ['code' => 'P-CHOIR', 'name' => 'Choir A'], 'name'],
            'ministries' => ['ministries', Ministry::class, ['code' => 'P-MIN', 'name' => 'Ministry A'], 'name'],
        ];
    }

    /**
     * @param  class-string<DirectoryEntity>  $modelClass
     * @param  array<string, string>  $attributes
     */
    #[DataProvider('parishEntities')]
    public function test_parish_administrators_can_enter_and_update_each_level_before_deanery_assignment(string $entity, string $modelClass, array $attributes, string $nameField): void
    {
        $parish = Parish::factory()->create(['deanery_id' => null]);
        $attributes = $this->withRequiredParents($entity, $attributes, $parish);
        $administrator = $this->administrator('parish_admin', ['parish_id' => $parish->id]);

        $id = $this->postJson('/api/v1/admin/'.$entity, $attributes)
            ->assertCreated()->assertJsonPath('data.parish_id', $parish->id)->json('data.id');

        $this->assertDatabaseHas((new $modelClass)->getTable(), ['id' => $id, 'parish_id' => $parish->id] + $attributes);
        $this->assertDatabaseHas('audit_logs', ['user_id' => $administrator->id, 'entity_type' => $entity, 'entity_id' => $id, 'action' => 'created']);
        $this->getJson('/api/v1/admin/'.$entity)->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $id);
        $this->patchJson('/api/v1/admin/'.$entity.'/'.$id, [$nameField => 'Updated record'])
            ->assertOk()->assertJsonPath('data.'.$nameField, 'Updated record');
        $this->assertDatabaseHas((new $modelClass)->getTable(), ['id' => $id, 'parish_id' => $parish->id, $nameField => 'Updated record']);
    }

    /**
     * @param  class-string<DirectoryEntity>  $modelClass
     * @param  array<string, string>  $attributes
     */
    #[DataProvider('parishEntities')]
    public function test_parish_administrators_cannot_read_or_write_another_parish_at_any_level(string $entity, string $modelClass, array $attributes, string $nameField): void
    {
        $parish = Parish::factory()->create();
        $otherParish = Parish::factory()->create();
        $otherRecord = $modelClass::factory()->recycle($otherParish)->create(['parish_id' => $otherParish->id]);
        $attributes = $this->withRequiredParents($entity, $attributes, $otherParish);
        $this->administrator('parish_admin', ['parish_id' => $parish->id]);

        $this->postJson('/api/v1/admin/'.$entity, $attributes + ['parish_id' => $otherParish->id])->assertForbidden();
        $this->getJson('/api/v1/admin/'.$entity.'?parish_id='.$otherParish->id)->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/admin/'.$entity.'/'.$otherRecord->id)->assertNotFound();
        $this->patchJson('/api/v1/admin/'.$entity.'/'.$otherRecord->id, [$nameField => 'Unauthorized change'])->assertForbidden();

        $this->assertDatabaseHas($otherRecord->getTable(), ['id' => $otherRecord->id, $nameField => $otherRecord->{$nameField}]);
        $this->assertDatabaseMissing($otherRecord->getTable(), $attributes);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_parish_administrator_can_update_own_contacts_but_cannot_edit_ancestors_or_create_parishes(): void
    {
        $parish = Parish::factory()->create();
        $this->administrator('parish_admin', ['parish_id' => $parish->id]);

        $this->patchJson('/api/v1/admin/parishes/'.$parish->id, ['phone' => '+255712345678', 'latitude' => -6.8, 'longitude' => 39.2])
            ->assertOk()->assertJsonMissingPath('data.verification_status');
        $this->assertDatabaseHas('parishes', ['id' => $parish->id, 'phone' => '+255712345678', 'latitude' => -6.8, 'longitude' => 39.2]);
        $this->patchJson('/api/v1/admin/deaneries/'.$parish->deanery_id, ['name' => 'Unauthorized'])->assertForbidden();
        $this->postJson('/api/v1/admin/parishes', ['code' => 'UNAUTHORIZED', 'name' => 'Another parish', 'deanery_id' => $parish->deanery_id])->assertForbidden();
        $this->assertDatabaseMissing('deaneries', ['name' => 'Unauthorized']);
        $this->assertDatabaseCount('parishes', 1);
    }

    public function test_automatic_parish_assignment_still_rejects_foreign_parents_and_null_parish_ids(): void
    {
        $parish = Parish::factory()->create();
        $otherZone = Zone::factory()->create();
        $this->administrator('parish_admin', ['parish_id' => $parish->id]);

        $this->postJson('/api/v1/admin/jumuiyas', ['code' => 'CROSS-PARISH', 'name' => 'Jumuiya', 'zone_id' => $otherZone->id])
            ->assertUnprocessable()->assertJsonPath('error.details.zone_id.0', 'The selected records must have consistent ancestry within the same parish.');
        $this->postJson('/api/v1/admin/outstations', ['code' => 'NULL-PARISH', 'name' => 'Kigango', 'parish_id' => null])->assertUnprocessable();

        $this->assertDatabaseCount('jumuiyas', 0);
        $this->assertDatabaseCount('outstations', 0);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_parish_assignment_is_not_inferred_for_national_administrators_or_missing_scope(): void
    {
        $parish = Parish::factory()->create();
        $this->administrator();
        $this->postJson('/api/v1/admin/outstations', ['code' => 'NO-PARISH', 'name' => 'Kigango'])->assertUnprocessable();

        $this->administrator('parish_admin');
        $this->postJson('/api/v1/admin/outstations', ['code' => 'NO-SCOPE', 'name' => 'Kigango', 'parish_id' => $parish->id])->assertForbidden();

        $this->assertDatabaseCount('outstations', 0);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_private_structure_includes_inactive_records_and_personal_data_only_for_the_assigned_parish(): void
    {
        $parish = Parish::factory()->create(['deanery_id' => null, 'phone' => '+255712345678']);
        $otherParish = Parish::factory()->create();
        $outstation = Outstation::factory()->create(['parish_id' => $parish->id, 'status' => 'inactive']);
        $zone = Zone::factory()->create(['parish_id' => $parish->id]);
        $records = [
            'outstations' => $outstation,
            'zones' => $zone,
            'jumuiyas' => Jumuiya::factory()->create(['parish_id' => $parish->id, 'zone_id' => $zone->id]),
            'associations' => Association::factory()->create(['parish_id' => $parish->id]),
            'choirs' => Choir::factory()->create(['parish_id' => $parish->id]),
            'ministries' => Ministry::factory()->create(['parish_id' => $parish->id]),
            'families' => Family::factory()->create(['parish_id' => $parish->id, 'phone' => 'PRIVATE-FAMILY']),
            'members' => Member::factory()->create(['parish_id' => $parish->id, 'first_name' => 'PRIVATE-MEMBER']),
        ];
        Family::factory()->create(['parish_id' => $otherParish->id]);
        $this->administrator('parish_admin', ['parish_id' => $parish->id]);

        $response = $this->getJson('/api/v1/admin/parishes/'.$parish->id.'/structure?parish_id='.$otherParish->id)
            ->assertOk()->assertJsonPath('data.parish.id', $parish->id)
            ->assertJsonPath('data.parish.phone', '+255712345678')
            ->assertJsonPath('data.families.0.phone', 'PRIVATE-FAMILY')
            ->assertJsonPath('data.members.0.first_name', 'PRIVATE-MEMBER');

        foreach ($records as $entity => $record) {
            $response->assertJsonCount(1, 'data.'.$entity)->assertJsonPath('data.'.$entity.'.0.id', $record->id)
                ->assertJsonPath('meta.'.$entity.'.total', 1)->assertJsonPath('meta.'.$entity.'.truncated', false)
                ->assertJsonPath('meta.'.$entity.'.url', url('/api/v1/admin/'.$entity).'?parish_id='.$parish->id);
        }
        $this->getJson('/api/v1/admin/parishes/'.$otherParish->id.'/structure')->assertNotFound();
        $this->administrator('parish_admin', ['parish_id' => $otherParish->id]);
        $this->getJson('/api/v1/admin/parishes/'.$parish->id.'/structure')->assertNotFound();
    }

    public static function supervisingRoles(): array
    {
        return [['super_admin'], ['tec_admin'], ['diocesan_admin'], ['deanery_admin']];
    }

    #[DataProvider('supervisingRoles')]
    public function test_supervising_administrators_can_read_structure_within_their_scope(string $role): void
    {
        $parish = Parish::factory()->create();
        $scope = match ($role) {
            'diocesan_admin' => ['diocese_id' => $parish->deanery->diocese_id],
            'deanery_admin' => ['deanery_id' => $parish->deanery_id],
            default => [],
        };
        $this->administrator($role, $scope);

        $this->getJson('/api/v1/admin/parishes/'.$parish->id.'/structure')
            ->assertOk()->assertJsonPath('data.parish.id', $parish->id);
    }

    public function test_private_structure_requires_authentication_an_active_account_and_read_permission(): void
    {
        $parish = Parish::factory()->create();
        $url = '/api/v1/admin/parishes/'.$parish->id.'/structure';

        $this->getJson($url)->assertUnauthorized();
        $this->administrator('parish_admin');
        $this->getJson($url)->assertNotFound();
        $this->administrator('parish_admin', ['parish_id' => $parish->id], ['directory:write']);
        $this->getJson($url)->assertForbidden();
        $user = $this->administrator('parish_admin', ['parish_id' => $parish->id], ['directory:read']);
        $this->getJson($url)->assertOk();
        $this->postJson('/api/v1/admin/outstations', ['code' => 'READ-ONLY', 'name' => 'Kigango'])->assertForbidden();
        $user->is_active = false;
        $user->save();
        $this->getJson($url)->assertForbidden();
        $this->assertDatabaseCount('outstations', 0);
    }

    public function test_public_structure_stays_public_after_a_private_request_even_with_an_administrator_token(): void
    {
        $parish = Parish::factory()->create(['phone' => 'PRIVATE-PARISH']);
        Family::factory()->create(['parish_id' => $parish->id]);
        Member::factory()->create(['parish_id' => $parish->id]);
        Zone::factory()->create(['parish_id' => $parish->id]);
        $this->administrator('parish_admin', ['parish_id' => $parish->id]);
        $this->getJson('/api/v1/admin/parishes/'.$parish->id.'/structure')->assertOk()->assertJsonCount(1, 'data.members');

        $this->getJson('/api/v1/parishes/'.$parish->id.'/structure')->assertOk()
            ->assertJsonMissingPath('data.families')->assertJsonMissingPath('data.members')
            ->assertJsonMissingPath('data.parish.phone')->assertJsonCount(1, 'data.zones')
            ->assertJsonMissingPath('meta.members')
            ->assertJsonPath('meta.zones.url', url('/api/v1/zones').'?parish_id='.$parish->id);
    }

    public function test_large_private_structures_provide_scoped_pagination_links_and_accurate_totals(): void
    {
        $parish = Parish::factory()->create();
        $families = Family::factory()->count(101)->create(['parish_id' => $parish->id, 'family_name' => 'Family']);
        Family::factory()->create();
        $this->administrator('parish_admin', ['parish_id' => $parish->id]);

        $response = $this->getJson('/api/v1/admin/parishes/'.$parish->id.'/structure')->assertOk()
            ->assertJsonCount(100, 'data.families')->assertJsonPath('meta.families.total', 101)
            ->assertJsonPath('meta.families.truncated', true);

        $this->getJson($response->json('meta.families.url').'&per_page=100&page=2')->assertOk()
            ->assertJsonPath('meta.total', 101)->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $families->last()->id);
    }

    public function test_parish_accounts_can_be_provisioned_and_used_to_log_in_and_enter_data(): void
    {
        $this->seed(AccessControlSeeder::class);
        $parish = Parish::factory()->create(['deanery_id' => null]);

        $this->artisan('core:create-admin', ['email' => 'parish@example.test', '--name' => 'Parish Administrator', '--role' => 'parish_admin', '--scope' => $parish->id])
            ->expectsQuestion('Password (at least 12 characters, with mixed case, numbers, and symbols)', 'Parish-Test-123!')
            ->assertSuccessful();

        $user = User::where('email', 'parish@example.test')->firstOrFail();
        $this->assertSame($parish->id, $user->parish_id);
        $this->assertSame('parish_admin', $user->role->name);
        $this->assertTrue(Hash::check('Parish-Test-123!', $user->password));
        $token = $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'Parish-Test-123!', 'device_name' => 'Parish office'])
            ->assertOk()->assertJsonPath('data.user.parish_id', $parish->id)->json('data.token');
        $this->withToken($token)->postJson('/api/v1/families', ['family_code' => 'PARISH-FAMILY', 'family_name' => 'Familia'])
            ->assertCreated()->assertJsonPath('data.parish_id', $parish->id);
        $this->assertDatabaseHas('families', ['parish_id' => $parish->id, 'family_code' => 'PARISH-FAMILY']);
    }

    /**
     * @param  array<string, string>  $attributes
     * @return array<string, int|string>
     */
    private function withRequiredParents(string $entity, array $attributes, Parish $parish): array
    {
        if ($entity === 'jumuiyas') {
            $attributes['zone_id'] = Zone::factory()->create(['parish_id' => $parish->id])->id;
        }

        return $attributes;
    }
}
