<?php

namespace Tests\Feature;

use App\Models\Member;
use App\Models\User;
use App\Rules\UniqueMemberPhone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

class MemberPhoneAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<string> */
    private array $sentCodes = [];
    private int $smsStatus = 202;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'member_auth.password_login_enabled' => false,
            'member_auth.sms.driver' => 'webhook',
            'member_auth.sms.url' => 'https://sms.example.test/send',
            'member_auth.sms.token' => 'test-token',
        ]);
        Http::preventStrayRequests();
        Http::fake(['https://sms.example.test/send' => function (Request $request): Response {
            preg_match('/\b([0-9]{6})\b/', $request['message'], $matches);
            $this->sentCodes[] = $matches[1];

            return Http::response(['accepted' => $this->smsStatus === 202], $this->smsStatus);
        }]);
    }

    public function test_verified_phone_creates_an_account_and_issues_a_scoped_token_once(): void
    {
        $member = Member::factory()->create(['phone' => '+255712345678', 'email' => null]);
        $challenge = $this->requestCode('0712345678');
        $response = $this->verify($challenge, $this->sentCodes[0])->assertOk()
            ->assertJsonPath('data.member.id', $member->id)
            ->assertJsonPath('data.member.email', null)
            ->assertJsonPath('data.member.parish.id', $member->parish_id);
        $this->assertDatabaseHas('users', ['member_id' => $member->id]);
        $this->assertSame(['member:read'], PersonalAccessToken::firstOrFail()->abilities);
        $this->assertNotNull(PersonalAccessToken::firstOrFail()->expires_at);
        $this->verify($challenge, $this->sentCodes[0])->assertUnprocessable();
        $this->assertDatabaseCount('personal_access_tokens', 1);
        $this->withToken($response->json('data.token'))->getJson('/api/v1/auth/me')->assertForbidden();
    }

    public function test_codes_are_not_returned_or_stored_as_plaintext(): void
    {
        Member::factory()->create(['phone' => '+255712345678']);
        $response = $this->postJson('/api/v1/member/auth/otp/request', ['phone' => '0712345678'])
            ->assertOk()->assertJsonMissingPath('data.code')->assertJsonMissingPath('data.development_code')->assertJsonMissingPath('data.member_id');
        $challenge = Cache::get('member-otp:challenge:'.$response->json('data.challenge_id'));
        $this->assertNotSame($this->sentCodes[0], $challenge['code_hash']);
        $this->assertArrayNotHasKey('code', $challenge);
    }

    public function test_unknown_duplicate_and_inactive_phones_never_receive_access(): void
    {
        $unknown = $this->requestCode('+255712345678');
        Http::assertNothingSent();
        $this->verify($unknown, '000000')->assertUnprocessable();
        Member::factory()->create(['phone' => '+255713345678']);
        Member::factory()->create(['phone' => '0713345678']);
        $duplicate = $this->requestCode('+255713345678');
        Http::assertNothingSent();
        $this->verify($duplicate, '000000')->assertUnprocessable();
        Member::factory()->create(['phone' => '+255714345678', 'status' => 'inactive']);
        $inactive = $this->requestCode('+255714345678');
        Http::assertNothingSent();
        $this->verify($inactive, '000000')->assertUnprocessable();
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_incorrect_attempts_and_expiration_invalidate_codes(): void
    {
        Member::factory()->create(['phone' => '+255712345678']);
        $challenge = $this->requestCode('+255712345678');
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->verify($challenge, '000000')->assertUnprocessable();
        }
        $this->verify($challenge, $this->sentCodes[0])->assertUnprocessable();
        $this->travel(61)->seconds();
        $expired = $this->requestCode('+255712345678');
        $this->travel(301)->seconds();
        $this->verify($expired, $this->sentCodes[1])->assertUnprocessable();
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_resending_replaces_old_codes_and_enforces_cooldown(): void
    {
        Member::factory()->create(['phone' => '+255712345678']);
        $old = $this->requestCode('0712345678');
        $this->postJson('/api/v1/member/auth/otp/request', ['phone' => '+255712345678'])->assertTooManyRequests();
        $this->travel(61)->seconds();
        $new = $this->requestCode('+255712345678');
        $this->verify($old, $this->sentCodes[0])->assertUnprocessable();
        $this->verify($new, $this->sentCodes[1])->assertOk();
    }

    public function test_changed_phones_revoke_sessions_and_invalidate_pending_codes(): void
    {
        $member = Member::factory()->create(['phone' => '+255712345678']);
        $challenge = $this->requestCode('+255712345678');
        $user = User::factory()->create(['member_id' => $member->id]);
        $user->createToken('old phone', ['member:read'], now()->addHour());
        $member->update(['phone' => '+255713345678']);
        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->verify($challenge, $this->sentCodes[0])->assertUnprocessable();
    }

    public function test_member_cannot_change_their_login_phone_through_profile_updates(): void
    {
        $member = Member::factory()->create(['phone' => '+255712345678']);
        $user = User::factory()->create(['member_id' => $member->id]);
        $token = $user->createToken('phone', ['member:read'], now()->addHour())->plainTextToken;
        $this->withToken($token)->patchJson('/api/v1/member/profile', ['phone' => '+255713345678'])
            ->assertUnprocessable()->assertJsonValidationErrors('phone', 'error.details');
        $this->assertSame('+255712345678', $member->fresh()->phone);
    }

    public function test_phone_registration_rejects_equivalent_numbers_and_accepts_own_number(): void
    {
        $member = Member::factory()->create(['phone' => '+255712345678']);
        $this->assertTrue(Validator::make(['phone' => '0712 345 678'], ['phone' => new UniqueMemberPhone])->fails());
        $this->assertTrue(Validator::make(['phone' => '0712345678'], ['phone' => new UniqueMemberPhone($member->id)])->passes());
    }

    public function test_disabled_sms_never_falls_back_to_preview_codes_or_passwords(): void
    {
        config(['member_auth.sms.driver' => 'disabled']);
        Member::factory()->create(['phone' => '+255712345678']);
        $this->postJson('/api/v1/member/auth/otp/request', ['phone' => '+255712345678'])
            ->assertServiceUnavailable()->assertJsonMissingPath('data.code');
        Http::assertNothingSent();
        $this->postJson('/api/v1/member/auth/login', ['identity' => 'member', 'password' => 'password', 'device_name' => 'phone'])
            ->assertStatus(410);
    }

    public function test_existing_accounts_are_reused_and_inactive_ancestors_block_login(): void
    {
        $member = Member::factory()->create(['phone' => '+255712345678']);
        $user = User::factory()->create(['member_id' => $member->id]);
        $challenge = $this->requestCode('+255712345678');
        $this->verify($challenge, $this->sentCodes[0])->assertOk();
        $this->assertSame($user->id, User::query()->where('member_id', $member->id)->sole()->id);
        $this->travel(61)->seconds();
        $pending = $this->requestCode('+255712345678');
        $member->parish->update(['status' => 'inactive']);
        $this->verify($pending, $this->sentCodes[1])->assertUnprocessable();
        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    public function test_sms_delivery_failure_does_not_create_a_challenge(): void
    {
        Member::factory()->create(['phone' => '+255712345678']);
        $this->smsStatus = 500;
        $this->postJson('/api/v1/member/auth/otp/request', ['phone' => '+255712345678'])
            ->assertServiceUnavailable()->assertJsonMissingPath('data.challenge_id');
        $this->assertNull(Cache::get('member-otp:active:'.hash('sha256', '+255712345678')));
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_preview_returns_a_random_code_without_sending_sms(): void
    {
        config(['member_auth.sms.driver' => 'preview']);
        Member::factory()->create(['phone' => '+255712345678']);
        $response = $this->postJson('/api/v1/member/auth/otp/request', ['phone' => '+255712345678'])->assertOk();
        $code = $response->json('data.development_code');
        $this->assertMatchesRegularExpression('/^[1-9][0-9]{5}$/', $code);
        $challenge = Cache::get('member-otp:challenge:'.$response->json('data.challenge_id'));
        $this->assertNotSame($code, $challenge['code_hash']);
        $this->assertArrayNotHasKey('development_code', $challenge);
        Http::assertNothingSent();
        $this->verify($response->json('data.challenge_id'), $code)->assertOk();
        $this->verify($response->json('data.challenge_id'), $code)->assertUnprocessable();
    }

    public function test_preview_never_grants_access_to_an_unregistered_number(): void
    {
        config(['member_auth.sms.driver' => 'preview']);
        $response = $this->postJson('/api/v1/member/auth/otp/request', ['phone' => '+255712345678'])->assertOk();
        $this->verify($response->json('data.challenge_id'), $response->json('data.development_code'))->assertUnprocessable();
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_preview_is_unavailable_in_production(): void
    {
        config(['member_auth.sms.driver' => 'preview']);
        $this->app->instance('env', 'production');
        $this->postJson('/api/v1/member/auth/otp/request', ['phone' => '+255712345678'])
            ->assertServiceUnavailable()->assertJsonMissingPath('data.development_code');
        Http::assertNothingSent();
    }

    public function test_members_must_verify_again_after_three_calendar_months(): void
    {
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-01-31 12:00:00', 'UTC'));
        Member::factory()->create(['phone' => '+255712345678']);
        $challenge = $this->requestCode('+255712345678');
        $response = $this->verify($challenge, $this->sentCodes[0])->assertOk();
        $token = PersonalAccessToken::firstOrFail();
        $this->assertSame('2026-04-30 12:00:00', $token->expires_at->utc()->format('Y-m-d H:i:s'));
        $this->travelTo($token->expires_at->copy()->subSecond());
        $this->withToken($response->json('data.token'))->getJson('/api/v1/member/auth/me')->assertOk();
        $this->app['auth']->forgetGuards();
        $this->travelTo($token->expires_at->copy()->addSecond());
        $this->withToken($response->json('data.token'))->getJson('/api/v1/member/auth/me')->assertUnauthorized();
        $newChallenge = $this->requestCode('+255712345678');
        $this->verify($newChallenge, $this->sentCodes[1])->assertOk();
    }

    private function requestCode(string $phone): string
    {
        return $this->postJson('/api/v1/member/auth/otp/request', ['phone' => $phone])->assertOk()->json('data.challenge_id');
    }

    private function verify(string $challenge, string $code): \Illuminate\Testing\TestResponse
    {
        return $this->postJson('/api/v1/member/auth/otp/verify', ['challenge_id' => $challenge, 'code' => $code, 'device_name' => 'test phone']);
    }
}
