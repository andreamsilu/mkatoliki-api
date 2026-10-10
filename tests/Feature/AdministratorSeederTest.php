<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\AccessControlSeeder;
use Database\Seeders\AdministratorSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdministratorSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_seeds_only_the_requested_super_administrator_idempotently(): void
    {
        $this->seed(AccessControlSeeder::class);
        $role = Role::query()->where('name', 'super_admin')->firstOrFail();
        $legacyAdministrator = User::factory()->create([
            'name' => 'TEC Administrator',
            'email' => 'admin@example.org',
            'password' => 'Previous-Admin-123!',
            'role_id' => $role->id,
        ]);
        $legacyAdministrator->createToken('existing-session');

        $this->seed(AdministratorSeeder::class);

        $administrator = User::query()->where('email', AdministratorSeeder::EMAIL)->firstOrFail();

        $this->assertSame($legacyAdministrator->id, $administrator->id);
        $this->assertSame('super_admin', $administrator->role?->name);
        $this->assertNull($administrator->parish_id);
        $this->assertTrue($administrator->is_active);
        $this->assertTrue(Hash::check(AdministratorSeeder::PASSWORD, $administrator->password));
        $this->assertSame(0, $administrator->tokens()->count());
        $this->assertDatabaseMissing('users', ['email' => 'admin@example.org']);
        $this->assertDatabaseMissing('users', ['email' => 'mabibo@mkatolik.co.tz']);

        $this->seed(AdministratorSeeder::class);

        $this->assertSame(1, User::query()->where('email', AdministratorSeeder::EMAIL)->count());
    }
}
