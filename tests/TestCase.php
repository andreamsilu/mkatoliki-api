<?php

namespace Tests;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\AccessControlSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Sanctum\Sanctum;

abstract class TestCase extends BaseTestCase
{
    protected function administrator(string $role = 'super_admin', array $scope = [], array $abilities = ['*']): User
    {
        $this->seed(AccessControlSeeder::class);
        $user = User::factory()->create(['role_id' => Role::where('name', $role)->value('id')] + $scope);
        Sanctum::actingAs($user, $abilities);

        return $user;
    }
}
