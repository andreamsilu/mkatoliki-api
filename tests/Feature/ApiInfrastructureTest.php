<?php

namespace Tests\Feature;

use App\Models\Family;
use App\Models\Jumuiya;
use App\Models\Outstation;
use App\Models\Parish;
use App\Models\Zone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ApiInfrastructureTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_checks_database_and_cache(): void
    {
        $this->getJson('/health')->assertOk()->assertJson(['status' => 'ok', 'database' => 'ok', 'cache' => 'ok', 'version' => '1.0.0']);
        Cache::shouldReceive('put')->once()->andThrow(new \RuntimeException('Unavailable'));
        $this->getJson('/health')->assertStatus(503)->assertJsonPath('cache', 'unavailable');
    }

    public function test_public_rate_limit_has_standard_json_errors(): void
    {
        config(['core.public_rate_limit' => 2]);
        $this->getJson('/api/v1/provinces')->assertOk();
        $this->getJson('/api/v1/provinces')->assertOk();
        $this->getJson('/api/v1/provinces')->assertStatus(429)->assertJsonPath('error.code', 'RATE_LIMIT_EXCEEDED')->assertHeader('Retry-After')->assertHeader('X-Request-ID');
    }

    public function test_transitive_ancestry_cannot_be_hidden_by_nullable_links(): void
    {
        $parish = Parish::factory()->create();
        $outstation = Outstation::factory()->create(['parish_id' => $parish->id]);
        $zone = Zone::factory()->create(['parish_id' => $parish->id, 'outstation_id' => $outstation->id]);
        $jumuiya = Jumuiya::factory()->create(['parish_id' => $parish->id, 'zone_id' => $zone->id]);
        $family = Family::factory()->create(['parish_id' => $parish->id, 'jumuiya_id' => $jumuiya->id, 'zone_id' => null, 'outstation_id' => null]);
        $otherZone = Zone::factory()->create(['parish_id' => $parish->id]);
        $otherOutstation = Outstation::factory()->create(['parish_id' => $parish->id]);
        $this->administrator();
        $base = ['member_code' => 'M-ANCESTRY', 'first_name' => 'Member', 'last_name' => 'One', 'gender' => 'male', 'parish_id' => $parish->id, 'family_id' => $family->id];
        $this->postJson('/api/v1/members', $base + ['zone_id' => $otherZone->id])->assertUnprocessable();
        $this->postJson('/api/v1/members', $base + ['outstation_id' => $otherOutstation->id])->assertUnprocessable();
        $this->postJson('/api/v1/members', $base)->assertCreated();
    }

    public function test_openapi_documents_every_versioned_operation(): void
    {
        $spec = json_decode(file_get_contents(base_path('docs/api/openapi.json')), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame('3.0.3', $spec['openapi']);
        foreach (Route::getRoutes() as $route) {
            if (! str_starts_with($route->uri(), 'api/v1/')) {
                continue;
            }
            $path = substr($route->uri(), strlen('api/v1'));
            foreach (array_diff($route->methods(), ['HEAD']) as $method) {
                $this->assertArrayHasKey(strtolower($method), $spec['paths'][$path] ?? [], "$method $path is undocumented");
            }
        }
        $this->get('/api/openapi.json')->assertOk()->assertHeader('Content-Type', 'application/json');
    }

    public function test_swagger_ui_loads_the_openapi_contract_and_supports_authorization(): void
    {
        $this->get('/swagger')->assertOk()
            ->assertSee('SwaggerUIBundle', false)
            ->assertSee(str_replace('/', '\\/', route('openapi')), false)
            ->assertSee('persistAuthorization: true', false);
        $this->get('/')->assertOk()
            ->assertJsonPath('documentation', route('swagger'))
            ->assertJsonPath('openapi', route('openapi'));
    }

    public function test_invalid_methods_and_unknown_resources_are_json_even_without_accept_header(): void
    {
        $this->get('/api/v1/missing')->assertNotFound()->assertJsonPath('success', false);
        $this->post('/api/v1/parishes')->assertStatus(405)->assertJsonPath('error.code', 'METHOD_NOT_ALLOWED');
    }
}
