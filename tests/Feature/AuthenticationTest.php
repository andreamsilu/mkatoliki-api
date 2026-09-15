<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\AccessControlSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_issues_expiring_hashed_token_and_logout_revokes_it(): void
    {
        $this->seed(AccessControlSeeder::class);
        $user = User::factory()->create(['email' => 'admin@example.test', 'password' => 'secure-password', 'role_id' => Role::where('name', 'super_admin')->value('id')]);
        $response = $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'secure-password', 'device_name' => 'test'])->assertOk();
        $token = $response->json('data.token');
        $stored = PersonalAccessToken::firstOrFail();
        $this->assertNotSame($token, $stored->token);
        $this->assertNotNull($stored->expires_at);
        $this->withToken($token)->getJson('/api/v1/auth/me')->assertOk()->assertJsonPath('data.id', $user->id)->assertJsonMissingPath('data.password');
        $this->withToken($token)->postJson('/api/v1/auth/logout')->assertOk();
        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/v1/auth/me')->assertUnauthorized();
    }

    public function test_invalid_credentials_and_disabled_accounts_are_rejected(): void
    {
        $this->seed(AccessControlSeeder::class);
        $user = User::factory()->create(['password' => 'password', 'is_active' => false, 'role_id' => Role::where('name', 'super_admin')->value('id')]);
        $payload = ['email' => $user->email, 'password' => 'password', 'device_name' => 'test'];
        $this->postJson('/api/v1/auth/login', $payload)->assertUnauthorized()->assertJsonPath('error.code', 'INVALID_CREDENTIALS');
        $this->postJson('/api/v1/auth/login', array_replace($payload, ['email' => 'unknown@example.test']))->assertUnauthorized();
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_expired_tokens_and_accounts_disabled_after_issuance_are_rejected(): void
    {
        $this->seed(AccessControlSeeder::class);
        $user = User::factory()->create(['role_id' => Role::where('name', 'super_admin')->value('id')]);
        $expired = $user->createToken('expired', ['*'], now()->subMinute())->plainTextToken;
        $this->withToken($expired)->getJson('/api/v1/auth/me')->assertUnauthorized();
        $valid = $user->createToken('valid', ['*'], now()->addHour())->plainTextToken;
        $user->update(['is_active' => false]);
        // is_active is deliberately not mass assignable to ordinary account updates.
        $user->is_active = false;
        $user->save();
        $this->app['auth']->forgetGuards();
        $this->withToken($valid)->getJson('/api/v1/auth/me')->assertForbidden();
    }

    public function test_login_is_rate_limited_and_errors_preserve_request_id(): void
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson('/api/v1/auth/login', [])->assertUnprocessable()->assertHeader('X-Request-ID');
        }
        $this->postJson('/api/v1/auth/login', [])->assertStatus(429)->assertJsonPath('error.code', 'RATE_LIMIT_EXCEEDED')->assertHeader('Retry-After')->assertHeader('X-Request-ID');
    }
}
