<?php

namespace Tests\Feature;

use App\Models\Deanery;
use App\Models\Diocese;
use App\Models\EcclesiasticalProvince;
use App\Models\Parish;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_requires_authentication(): void
    {
        $this->postJson('/api/v1/admin/dashboard')->assertUnauthorized();
    }

    public function test_dashboard_returns_scope_aware_directory_counts(): void
    {
        $province = EcclesiasticalProvince::factory()->create();
        $diocese = Diocese::factory()->create(['ecclesiastical_province_id' => $province->id]);
        $deanery = Deanery::factory()->create(['diocese_id' => $diocese->id]);
        Parish::factory()->count(2)->create(['deanery_id' => $deanery->id]);
        $this->administrator();

        $this->postJson('/api/v1/admin/dashboard')
            ->assertOk()
            ->assertJsonPath('data.counts.provinces', 1)
            ->assertJsonPath('data.counts.dioceses', 1)
            ->assertJsonPath('data.counts.deaneries', 1)
            ->assertJsonPath('data.counts.parishes', 2)
            ->assertJsonPath('data.scope.role', 'super_admin');
    }
}
