<?php

namespace Tests\Feature;

use App\Models\Parish;
use App\Models\ParishAnnouncement;
use App\Models\ParishEvent;
use App\Models\ParishMassTime;
use App\Models\ParishProject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ParishContentApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_parish_content_contains_only_published_records(): void
    {
        $parish = Parish::factory()->create(['name' => 'St. Maria Parish', 'address' => 'Dar es Salaam']);
        ParishMassTime::factory()->for($parish)->create(['day_label' => 'Sunday', 'time_label' => '7:00 AM']);
        ParishAnnouncement::factory()->for($parish)->create(['title' => 'Published notice']);
        ParishAnnouncement::factory()->for($parish)->create(['title' => 'Draft notice', 'is_published' => false]);
        ParishEvent::factory()->for($parish)->create(['title' => 'Parish retreat']);
        ParishProject::factory()->for($parish)->create(['title' => 'Church renovation', 'progress_percentage' => 65]);

        $this->getJson("/api/v1/parishes/{$parish->id}/content")
            ->assertOk()
            ->assertJsonPath('data.name', 'St. Maria Parish')
            ->assertJsonPath('data.mass_times.0.day', 'Sunday')
            ->assertJsonPath('data.announcements.0.title', 'Published notice')
            ->assertJsonMissing(['title' => 'Draft notice'])
            ->assertJsonPath('data.events.0.title', 'Parish retreat')
            ->assertJsonPath('data.projects.0.progress', 0.65);
    }

    public function test_unverified_active_parish_content_is_public(): void
    {
        $parish = Parish::factory()->create(['verification_status' => 'pending', 'verified_at' => null]);
        ParishAnnouncement::factory()->for($parish)->create(['title' => 'Visible before verification']);

        $this->getJson("/api/v1/parishes/{$parish->id}/content")
            ->assertOk()
            ->assertJsonPath('data.announcements.0.title', 'Visible before verification');
    }

    public function test_inactive_parish_content_is_not_public(): void
    {
        $parish = Parish::factory()->create(['status' => 'inactive']);

        $this->getJson("/api/v1/parishes/{$parish->id}/content")
            ->assertNotFound();
    }

    public function test_parish_content_is_hidden_when_an_ancestor_is_inactive(): void
    {
        $parish = Parish::factory()->create();
        $parish->deanery->update(['status' => 'inactive']);

        $this->getJson("/api/v1/parishes/{$parish->id}/content")
            ->assertNotFound();
    }
}
