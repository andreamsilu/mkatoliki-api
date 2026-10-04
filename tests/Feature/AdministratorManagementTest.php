<?php

namespace Tests\Feature;

use App\Models\Parish;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\AccessControlSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

class AdministratorManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_administrator_can_create_multiple_accounts_for_one_parish(): void
    {
        $actor = $this->administrator();
        $parish = Parish::factory()->create();
        $payload = [
            'name' => 'Parish Administrator One',
            'email' => 'admin.one@example.test',
            'password' => 'Secure-Admin-123!',
            'password_confirmation' => 'Secure-Admin-123!',
            'parish_id' => $parish->id,
        ];

        $this->postJson('/api/v1/admin/administrators', $payload)
            ->assertCreated()
            ->assertJsonPath('data.role', 'parish_admin')
            ->assertJsonPath('data.parish_id', $parish->id)
            ->assertJsonPath('data.is_active', true);

        $payload['name'] = 'Parish Administrator Two';
        $payload['email'] = 'admin.two@example.test';
        $this->postJson('/api/v1/admin/administrators', $payload)->assertCreated();

        $this->assertDatabaseCount('users', 3);
        $administrator = User::query()->where('email', 'admin.one@example.test')->firstOrFail();
        $this->assertTrue(Hash::check('Secure-Admin-123!', $administrator->password));
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $actor->id,
            'action' => 'administrator.created',
            'entity_type' => 'users',
            'entity_id' => $administrator->id,
        ]);
    }

    public function test_only_super_administrators_can_manage_parish_accounts(): void
    {
        $parish = Parish::factory()->create();
        $payload = [
            'name' => 'Parish Administrator',
            'email' => 'admin@example.test',
            'password' => 'Secure-Admin-123!',
            'password_confirmation' => 'Secure-Admin-123!',
            'parish_id' => $parish->id,
        ];

        foreach (['tec_admin', 'parish_admin'] as $role) {
            $scope = $role === 'parish_admin' ? ['parish_id' => $parish->id] : [];
            $this->administrator($role, $scope);
            $this->postJson('/api/v1/admin/administrators', $payload)->assertForbidden();
            $this->getJson('/api/v1/admin/administrators')->assertForbidden();
        }
    }

    public function test_super_administrator_can_list_deactivate_and_reset_a_parish_account(): void
    {
        $this->seed(AccessControlSeeder::class);
        $parish = Parish::factory()->create();
        $role = Role::query()->where('name', 'parish_admin')->firstOrFail();
        $administrator = User::factory()->create([
            'role_id' => $role->id,
            'parish_id' => $parish->id,
            'email' => 'parish@example.test',
            'password' => 'Original-Admin-123!',
        ]);
        $token = $administrator->createToken('parish-office', ['directory:read'])->accessToken;
        $this->administrator();

        $this->getJson('/api/v1/admin/administrators?parish_id='.$parish->id)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.email', 'parish@example.test');

        $this->patchJson('/api/v1/admin/administrators/'.$administrator->id, [
            'is_active' => false,
            'password' => 'Replacement-Admin-123!',
            'password_confirmation' => 'Replacement-Admin-123!',
        ])->assertOk()->assertJsonPath('data.is_active', false);

        $administrator->refresh();
        $this->assertTrue(Hash::check('Replacement-Admin-123!', $administrator->password));
        $this->assertSame(0, PersonalAccessToken::query()->whereKey($token->id)->count());
        $this->postJson('/api/v1/auth/login', [
            'email' => $administrator->email,
            'password' => 'Replacement-Admin-123!',
            'device_name' => 'test',
        ])->assertUnauthorized();
    }

    public function test_creation_requires_a_real_parish_and_strong_confirmed_password(): void
    {
        $this->administrator();

        $this->postJson('/api/v1/admin/administrators', [
            'name' => 'Parish Administrator',
            'email' => 'admin@example.test',
            'password' => 'weak',
            'password_confirmation' => 'different',
            'parish_id' => 999999,
        ])->assertUnprocessable()->assertJsonValidationErrors(['password', 'parish_id']);
    }
}
