<?php

namespace Tests\Feature;

use App\Models\Parish;
use App\Models\ParishPublishedReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ParishPublishedReportsTest extends TestCase
{
    use RefreshDatabase;

    private function report(Parish $parish, array $attributes = []): ParishPublishedReport
    {
        $report = new ParishPublishedReport;
        $report->parish_id = $parish->id;
        $report->fill($attributes + ['title' => 'Parish report', 'summary' => 'Published parish summary.', 'body' => 'Written parish report.', 'is_published' => true, 'published_at' => now()->subMinute()]);
        $report->save();

        return $report;
    }

    public function test_public_readers_only_see_due_published_reports_from_the_selected_active_parish(): void
    {
        $parish = Parish::factory()->create();
        $report = $this->report($parish);
        $draft = $this->report($parish, ['is_published' => false]);
        $future = $this->report($parish, ['published_at' => now()->addDay()]);
        $other = $this->report(Parish::factory()->create());
        $this->getJson("/api/v1/parishes/$parish->id/reports")->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $report->id)->assertJsonMissingPath('data.0.body')->assertJsonMissingPath('data.0.document_path');
        $this->getJson("/api/v1/parishes/$parish->id/reports/$report->id")->assertOk()->assertJsonPath('data.body', $report->body);
        foreach ([$draft, $future, $other] as $hidden) {
            $this->getJson("/api/v1/parishes/$parish->id/reports/$hidden->id")->assertNotFound();
        }
        $parish->update(['status' => 'inactive']);
        $this->getJson("/api/v1/parishes/$parish->id/reports")->assertNotFound();
    }

    public function test_parish_admin_creates_written_reports_and_withdraws_publication(): void
    {
        $parish = Parish::factory()->create();
        $this->administrator('parish_admin', ['parish_id' => $parish->id]);
        $payload = ['title' => 'Ripoti ya Parokia', 'summary' => 'Muhtasari', 'body' => 'Maandishi ya ripoti.', 'is_published' => true];
        $id = $this->postJson("/api/v1/admin/parishes/$parish->id/published-reports", $payload)->assertCreated()->json('data.id');
        $this->getJson("/api/v1/parishes/$parish->id/reports/$id")->assertOk();
        $this->patchJson("/api/v1/admin/published-reports/$id", ['is_published' => false] + $payload)->assertOk();
        $this->getJson("/api/v1/parishes/$parish->id/reports/$id")->assertNotFound();
        $this->assertDatabaseHas('audit_logs', ['action' => 'parish_report.saved', 'entity_id' => $id]);
    }

    public function test_pdf_only_reports_are_private_until_publication_and_reject_invalid_files(): void
    {
        Storage::fake('local');
        $parish = Parish::factory()->create();
        $this->administrator('parish_admin', ['parish_id' => $parish->id]);
        $payload = ['title' => 'PDF report', 'summary' => 'Summary', 'is_published' => '0'];
        $this->postJson("/api/v1/admin/parishes/$parish->id/published-reports", $payload)->assertUnprocessable();
        $this->post("/api/v1/admin/parishes/$parish->id/published-reports", $payload + ['document' => UploadedFile::fake()->create('bad.html', 1, 'text/html')], ['Accept' => 'application/json'])->assertUnprocessable();
        $response = $this->post("/api/v1/admin/parishes/$parish->id/published-reports", $payload + ['document' => UploadedFile::fake()->createWithContent('report.pdf', "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF")], ['Accept' => 'application/json'])
            ->assertCreated()->assertJsonPath('data.has_document', true)->assertJsonMissingPath('data.document_path');
        $id = $response->json('data.id');
        $this->get("/api/v1/admin/published-reports/$id/document")->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->get("/api/v1/parishes/$parish->id/reports/$id/document")->assertNotFound();
        $this->patchJson("/api/v1/admin/published-reports/$id", ['is_published' => true] + $payload)->assertOk();
        $this->get("/api/v1/parishes/$parish->id/reports/$id/document")->assertOk();
        $this->patchJson("/api/v1/admin/published-reports/$id", ['remove_document' => true] + $payload)->assertUnprocessable();
        $path = ParishPublishedReport::findOrFail($id)->document_path;
        $this->deleteJson("/api/v1/admin/published-reports/$id")->assertOk();
        Storage::disk('local')->assertMissing($path);
        $this->get("/api/v1/parishes/$parish->id/reports/$id/document")->assertNotFound();
    }

    public function test_other_parishes_and_read_only_tokens_cannot_manage_publications(): void
    {
        Storage::fake('local');
        $parish = Parish::factory()->create();
        $other = Parish::factory()->create();
        $report = $this->report($other);
        $this->administrator('parish_admin', ['parish_id' => $parish->id]);
        $payload = ['title' => 'Report', 'summary' => 'Summary', 'body' => 'Body', 'is_published' => true];
        $this->getJson("/api/v1/admin/parishes/$other->id/published-reports")->assertForbidden();
        $this->postJson("/api/v1/admin/parishes/$other->id/published-reports", $payload)->assertForbidden();
        $this->patchJson("/api/v1/admin/published-reports/$report->id", $payload)->assertForbidden();
        $this->get("/api/v1/admin/published-reports/$report->id/document")->assertForbidden();
        $this->administrator('parish_admin', ['parish_id' => $parish->id], ['directory:read']);
        $this->postJson("/api/v1/admin/parishes/$parish->id/published-reports", $payload)->assertForbidden();
    }

    public function test_reports_are_paginated_without_member_statistics(): void
    {
        $parish = Parish::factory()->create();
        for ($index = 0; $index < 3; $index++) { $this->report($parish); }
        $this->getJson("/api/v1/parishes/$parish->id/reports?per_page=2&page=2")->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('meta.total', 3)
            ->assertJsonMissingPath('data.0.members')->assertJsonMissingPath('data.0.contributions');
    }
}
