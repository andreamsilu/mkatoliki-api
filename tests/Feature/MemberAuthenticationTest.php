<?php

namespace Tests\Feature;

use App\Models\Member;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

class MemberAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_can_login_read_profile_and_logout(): void
    {
        [$member, $user] = $this->memberAccount();

        $response = $this->postJson('/api/v1/member/auth/login', [
            'identity' => $member->member_code,
            'password' => 'secure-password',
            'device_name' => 'test phone',
        ])->assertOk()
            ->assertJsonPath('data.member.id', $member->id)
            ->assertJsonPath('data.member.name', 'Asha Mrema')
            ->assertJsonPath('data.member.parish.name', $member->parish->name);

        $token = $response->json('data.token');
        $stored = PersonalAccessToken::firstOrFail();
        $this->assertNotSame($token, $stored->token);
        $this->assertSame(['member:read'], $stored->abilities);
        $this->assertNotNull($stored->expires_at);

        $this->withToken($token)->getJson('/api/v1/member/auth/me')
            ->assertOk()
            ->assertJsonPath('data.member_code', $member->member_code)
            ->assertJsonMissingPath('data.password');
        $this->withToken($token)->postJson('/api/v1/member/auth/logout')->assertOk();
        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/v1/member/auth/me')->assertUnauthorized();

        $this->assertSame($member->id, $user->fresh()->member_id);
    }

    public function test_invalid_inactive_or_unlinked_accounts_are_rejected(): void
    {
        [$member, $user] = $this->memberAccount();
        $payload = ['identity' => $user->email, 'password' => 'wrong-password', 'device_name' => 'test'];
        $this->postJson('/api/v1/member/auth/login', $payload)
            ->assertUnauthorized()
            ->assertJsonPath('error.code', 'INVALID_CREDENTIALS');

        $member->update(['status' => 'inactive']);
        $this->postJson('/api/v1/member/auth/login', array_replace($payload, ['password' => 'secure-password']))
            ->assertUnauthorized();

        $unlinked = User::factory()->create(['password' => 'secure-password']);
        $this->postJson('/api/v1/member/auth/login', array_replace($payload, ['identity' => $unlinked->email, 'password' => 'secure-password']))
            ->assertUnauthorized();
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_member_token_cannot_use_administrator_api_and_is_revoked_when_scope_becomes_inactive(): void
    {
        [$member, $user] = $this->memberAccount();
        $token = $user->createToken('phone', ['member:read'], now()->addHour())->plainTextToken;

        $this->withToken($token)->getJson('/api/v1/auth/me')->assertForbidden();
        $member->parish->deanery->update(['status' => 'inactive']);
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/v1/member/auth/me')->assertForbidden();
    }

    public function test_member_account_can_be_provisioned_for_an_existing_member(): void
    {
        $member = Member::factory()->create(['member_code' => 'DSM-00042', 'first_name' => 'Neema', 'last_name' => 'Juma']);

        $this->artisan('member:account', [
            'member_code' => $member->member_code,
            'email' => 'neema@example.test',
            '--password' => 'secure-password',
        ])->assertSuccessful();

        $this->assertDatabaseHas('users', ['email' => 'neema@example.test', 'member_id' => $member->id]);
        $this->postJson('/api/v1/member/auth/login', [
            'identity' => 'neema@example.test',
            'password' => 'secure-password',
            'device_name' => 'test phone',
        ])->assertOk()
            ->assertJsonPath('data.member.name', 'Neema Juma')
            ->assertJsonPath('data.member.email', 'neema@example.test');
    }

    /**
     * @return array{Member, User}
     */
    private function memberAccount(): array
    {
        $member = Member::factory()->create([
            'member_code' => 'DSM-00001',
            'first_name' => 'Asha',
            'middle_name' => null,
            'last_name' => 'Mrema',
            'email' => 'asha.member@example.test',
            'phone' => '+255712345678',
        ]);
        $user = User::factory()->create([
            'email' => 'asha.login@example.test',
            'password' => 'secure-password',
        ]);
        $user->member()->associate($member);
        $user->save();

        return [$member->fresh(['parish.deanery']), $user->fresh()];
    }
}
