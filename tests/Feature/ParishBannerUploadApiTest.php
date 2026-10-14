<?php

namespace Tests\Feature;

use App\Models\Parish;
use App\Models\ParishAnnouncement;
use App\Models\ParishEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ParishBannerUploadApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_parish_administrator_uploads_an_announcement_banner_exposed_by_admin_and_public_apis(): void
    {
        Storage::fake('public');
        $parish = Parish::factory()->create();
        $this->administrator('parish_admin', ['parish_id' => $parish->id]);

        $response = $this->post("/api/v1/admin/parishes/{$parish->id}/announcements", [
            'title' => 'Parish assembly',
            'summary' => 'All leaders should attend.',
            'category' => 'Parish',
            'published_at' => now()->subMinute()->toIso8601String(),
            'is_published' => '1',
            'banner' => $this->fakePng('assembly-banner.png'),
        ], ['Accept' => 'application/json']);

        $response->assertCreated()
            ->assertJsonPath('data.title', 'Parish assembly')
            ->assertJsonPath('data.banner_path', fn (mixed $path): bool => is_string($path) && str_starts_with($path, "parishes/{$parish->id}/announcements/"))
            ->assertJsonPath('data.banner_url', fn (mixed $url): bool => is_string($url) && str_contains($url, '/storage/parishes/'));

        $bannerPath = $response->json('data.banner_path');
        Storage::disk('public')->assertExists($bannerPath);

        $this->getJson("/api/v1/parishes/{$parish->id}/content")
            ->assertOk()
            ->assertJsonPath('data.announcements.0.banner_path', $bannerPath)
            ->assertJsonPath('data.announcements.0.banner_url', $response->json('data.banner_url'));
    }

    public function test_replacing_and_removing_an_announcement_banner_cleans_up_files(): void
    {
        Storage::fake('public');
        $parish = Parish::factory()->create();
        $announcement = ParishAnnouncement::factory()->for($parish)->create([
            'banner_path' => "parishes/{$parish->id}/announcements/old-banner.jpg",
        ]);
        Storage::disk('public')->put($announcement->banner_path, 'old banner');
        $this->administrator('parish_admin', ['parish_id' => $parish->id]);

        $replacement = $this->post("/api/v1/admin/announcements/{$announcement->id}", [
            '_method' => 'PATCH',
            'banner' => $this->fakePng('replacement.png'),
        ], ['Accept' => 'application/json']);

        $replacement->assertOk();
        $replacementPath = $replacement->json('data.banner_path');
        Storage::disk('public')->assertMissing($announcement->banner_path);
        Storage::disk('public')->assertExists($replacementPath);

        $this->post("/api/v1/admin/announcements/{$announcement->id}", [
            '_method' => 'PATCH',
            'remove_banner' => '1',
        ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.banner_path', null)
            ->assertJsonPath('data.banner_url', null);

        Storage::disk('public')->assertMissing($replacementPath);
    }

    public function test_event_banner_is_validated_and_removed_when_the_event_is_deleted(): void
    {
        Storage::fake('public');
        $parish = Parish::factory()->create();
        $this->administrator('parish_admin', ['parish_id' => $parish->id]);

        $this->post("/api/v1/admin/parishes/{$parish->id}/events", [
            'title' => 'Parish retreat',
            'starts_at' => now()->addWeek()->toIso8601String(),
            'venue' => 'Parish hall',
            'description' => 'Annual parish retreat.',
            'banner' => UploadedFile::fake()->create('document.pdf', 50, 'application/pdf'),
        ], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonValidationErrors('banner', 'error.details');

        $created = $this->post("/api/v1/admin/parishes/{$parish->id}/events", [
            'title' => 'Parish retreat',
            'starts_at' => now()->addWeek()->toIso8601String(),
            'venue' => 'Parish hall',
            'description' => 'Annual parish retreat.',
            'is_published' => '1',
            'banner' => $this->fakePng('retreat.png'),
        ], ['Accept' => 'application/json'])->assertCreated();

        $event = ParishEvent::findOrFail($created->json('data.id'));
        Storage::disk('public')->assertExists($event->banner_path);

        $this->deleteJson("/api/v1/admin/events/{$event->id}")->assertOk();
        Storage::disk('public')->assertMissing($event->banner_path);
    }

    private function fakePng(string $name): UploadedFile
    {
        $content = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true);

        if ($content === false) {
            $this->fail('The embedded PNG test fixture is invalid.');
        }

        return UploadedFile::fake()->createWithContent($name, $content);
    }
}
