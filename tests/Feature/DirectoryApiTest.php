<?php

namespace Tests\Feature;

use App\Models\Association;
use App\Models\Choir;
use App\Models\Family;
use App\Models\Jumuiya;
use App\Models\Member;
use App\Models\Ministry;
use App\Models\Outstation;
use App\Models\Parish;
use App\Models\Zone;
use App\Support\EntityRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DirectoryApiTest extends TestCase
{
    use RefreshDatabase;

    public static function publicEntities(): array
    {
        return array_map(fn ($entity) => [$entity], ['provinces', 'dioceses', 'deaneries', 'parishes', 'outstations', 'zones', 'jumuiyas', 'associations', 'choirs', 'ministries']);
    }

    #[DataProvider('publicEntities')]
    public function test_public_entities_have_paginated_lists_and_details(string $entity): void
    {
        $class = EntityRegistry::definition($entity)['model'];
        $record = $class::factory()->create();
        $this->postJson('/api/v1/'.$entity.'/search')->assertOk()->assertJsonPath('success', true)->assertJsonPath('data.0.id', $record->id)->assertJsonPath('meta.total', 1);
        $this->postJson('/api/v1/'.$entity.'/'.$record->id)->assertOk()->assertJsonPath('data.id', $record->id)->assertHeader('X-Request-ID');
    }

    public function test_nested_lists_are_bound_to_the_parent_even_with_conflicting_filters(): void
    {
        $parish = Parish::factory()->create();
        $zone = Zone::factory()->create(['parish_id' => $parish->id]);
        $other = Zone::factory()->create();
        $this->postJson("/api/v1/parishes/{$parish->id}/zones")->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $zone->id);
        $this->postJson("/api/v1/parishes/{$parish->id}/zones", ['parish_id' => $other->parish_id])->assertOk()->assertJsonCount(0, 'data');
        $this->postJson('/api/v1/parishes/999999/zones')->assertNotFound();
        $this->postJson("/api/v1/provinces/{$parish->deanery->diocese->ecclesiastical_province_id}/dioceses")->assertOk()->assertJsonPath('data.0.id', $parish->deanery->diocese_id);
        $this->postJson("/api/v1/dioceses/{$parish->deanery->diocese_id}/deaneries")->assertOk()->assertJsonPath('data.0.id', $parish->deanery_id);
        $this->postJson("/api/v1/deaneries/{$parish->deanery_id}/parishes")->assertOk()->assertJsonPath('data.0.id', $parish->id);
    }

    public function test_context_and_structure_exclude_personal_data(): void
    {
        $parish = Parish::factory()->create(['phone' => '+255712345678', 'email' => 'private@example.test', 'address' => 'Private office']);
        $outstation = Outstation::factory()->create(['parish_id' => $parish->id]);
        $zone = Zone::factory()->create(['parish_id' => $parish->id, 'outstation_id' => $outstation->id]);
        Jumuiya::factory()->create(['parish_id' => $parish->id, 'zone_id' => $zone->id]);
        Family::factory()->create(['parish_id' => $parish->id, 'phone' => 'SECRET-FAMILY']);
        Member::factory()->create(['parish_id' => $parish->id, 'first_name' => 'SECRET-MEMBER']);
        foreach ([Association::class, Choir::class, Ministry::class] as $class) {
            $class::factory()->create(['parish_id' => $parish->id]);
        }
        $this->postJson("/api/v1/parishes/{$parish->id}/context")->assertOk()->assertJsonPath('data.diocese.id', $parish->deanery->diocese_id)->assertJsonMissingPath('data.parish.phone');
        $response = $this->postJson("/api/v1/parishes/{$parish->id}/structure")->assertOk()->assertJsonCount(1, 'data.zones')->assertJsonCount(1, 'data.choirs')->assertJsonMissingPath('data.families')->assertJsonMissingPath('data.members');
        $this->assertStringNotContainsString('SECRET-', $response->getContent());
        $this->assertStringNotContainsString('private@example.test', $response->getContent());
        $this->postJson("/api/v1/zones/{$zone->id}/jumuiyas")->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_filters_search_and_pagination_are_consistent(): void
    {
        $parish = Parish::factory()->create(['name' => 'Parokia ya Kijenge']);
        Parish::factory()->create(['name' => 'Another parish']);
        Member::factory()->create(['first_name' => 'Kijenge', 'parish_id' => $parish->id]);
        $this->postJson('/api/v1/parishes/search', ['diocese_id' => $parish->deanery->diocese_id])->assertOk()->assertJsonCount(1, 'data');
        $this->postJson('/api/v1/parishes/search', ['per_page' => 1, 'page' => 2])->assertOk()->assertJsonPath('meta.total', 2)->assertJsonPath('meta.current_page', 2);
        $this->postJson('/api/v1/search', ['q' => 'kijenge'])->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.entity_type', 'parishes');
        $this->postJson('/api/v1/search', ['q' => '%%'])->assertOk()->assertJsonCount(0, 'data');
        $this->postJson('/api/v1/search')->assertUnprocessable()->assertJsonPath('error.code', 'VALIDATION_ERROR');
        $this->postJson('/api/v1/parishes/search', ['per_page' => 101])->assertUnprocessable();
        $this->postJson('/api/v1/parishes/search', ['status' => 'unknown'])->assertUnprocessable();
    }

    public function test_verification_does_not_gate_publication_but_inactive_records_and_hidden_ancestors_do(): void
    {
        $pending = Parish::factory()->create(['verification_status' => 'pending']);
        Parish::factory()->create(['status' => 'inactive']);
        $hidden = Parish::factory()->create();
        $hidden->deanery->update(['status' => 'suppressed']);
        Zone::factory()->create(['parish_id' => $pending->id]);
        $this->postJson('/api/v1/parishes/search')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $pending->id);
        $this->postJson('/api/v1/zones/search')->assertOk()->assertJsonCount(1, 'data');
        $this->postJson("/api/v1/parishes/{$pending->id}")->assertOk()->assertJsonPath('data.id', $pending->id);
    }

    public function test_family_and_member_routes_require_authentication_even_without_accept_header(): void
    {
        foreach (['families', 'members', 'admin/parishes'] as $entity) {
            $this->post('/api/v1/'.$entity.'/search')->assertUnauthorized()->assertJsonPath('error.code', 'UNAUTHENTICATED');
        }
    }
}
