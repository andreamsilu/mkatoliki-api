<?php

namespace Tests\Feature;

use App\Models\ContentBookmark;
use App\Models\ContentCategory;
use App\Models\ContentMedia;
use App\Models\ContentPost;
use App\Models\DailyReflection;
use App\Models\Member;
use App\Models\Role;
use App\Models\User;
use App\Services\ContentPublishing;
use Database\Seeders\AccessControlSeeder;
use Database\Seeders\ContentCategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CatholicContentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([AccessControlSeeder::class, ContentCategorySeeder::class]);
    }

    private function editor(string $role = 'tec_admin'): User
    {
        $user = User::factory()->create(['role_id' => Role::where('name', $role)->value('id')]);
        Sanctum::actingAs($user, ['directory:read', 'directory:write']);
        return $user;
    }

    private function payload(string $slug = 'maudhui-ya-majaribio'): array
    {
        return ['slug' => $slug, 'content_type' => 'teaching', 'format' => 'article', 'source_type' => 'editorial', 'rights_status' => 'original',
            'category_ids' => [ContentCategory::where('slug', 'mafundisho')->value('id')], 'tag_ids' => [],
            'translations' => [['locale' => 'sw', 'title' => 'Kichwa cha majaribio', 'summary' => 'Muhtasari wa majaribio.', 'body' => 'Maudhui ya majaribio, si mafundisho yaliyochapishwa.']]];
    }

    public function test_public_feed_and_detail_only_expose_published_independently_reviewed_posts(): void
    {
        $published = ContentPost::factory()->published()->create();
        $draft = ContentPost::factory()->create();
        ContentPost::factory()->create(['status' => 'in_review']);
        ContentPost::factory()->create(['status' => 'published', 'published_at' => now(), 'reviewer_id' => null]);
        $published->reviews()->create(['status' => 'approve', 'review_notes' => 'Private review', 'reviewed_at' => now()]);
        $this->getJson('/api/v1/content/feed')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $published->id)
            ->assertJsonMissingPath('data.0.reviews')->assertJsonMissingPath('data.0.translations')->assertJsonMissingPath('data.0.body');
        $this->getJson('/api/v1/content/posts/'.$draft->slug)->assertNotFound();
        $this->getJson('/api/v1/content/posts/'.$published->slug)->assertOk()->assertJsonPath('data.body', $published->translations->first()->body)->assertJsonMissingPath('data.reviewer_id');
    }

    public function test_filters_translation_search_and_pagination_use_the_database(): void
    {
        ContentPost::factory()->published()->count(4)->create();
        $post = ContentPost::factory()->published()->create(['season' => 'lent', 'format' => 'short']);
        $post->translations()->create(['locale' => 'en', 'title' => 'Distinct searchable title', 'summary' => 'Testing only', 'body' => 'English test body']);
        $this->getJson('/api/v1/content/feed?per_page=2&page=2')->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('meta.total', 5)->assertJsonPath('meta.last_page', 3);
        $this->getJson('/api/v1/content/search?locale=en&q=Distinct&season=lent&format=short&category=mafundisho')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.title', 'Distinct searchable title');
        $this->getJson('/api/v1/content/feed?locale=invalid')->assertUnprocessable();
        $this->getJson('/api/v1/content/feed?per_page=1000')->assertUnprocessable();
    }

    public function test_post_relationships_are_json_arrays_in_feed_and_detail(): void
    {
        $post = ContentPost::factory()->published()->create();
        $tag = \App\Models\ContentTag::create(['name' => 'Imani', 'slug' => 'imani']);
        $post->tags()->attach($tag);
        foreach (['/api/v1/content/feed', '/api/v1/content/posts/'.$post->slug] as $path) {
            $response = $this->getJson($path)->assertOk();
            $data = json_decode($response->getContent(), flags: JSON_THROW_ON_ERROR)->data;
            $record = is_array($data) ? $data[0] : $data;
            $this->assertIsArray($record->categories);
            $this->assertIsArray($record->tags);
            $this->assertSame('mafundisho', $record->categories[0]->slug);
            $this->assertSame('imani', $record->tags[0]->slug);
        }
        $post->tags()->detach();
        $response = $this->getJson('/api/v1/content/posts/'.$post->slug)->assertOk();
        $this->assertSame([], json_decode($response->getContent(), flags: JSON_THROW_ON_ERROR)->data->tags);
    }

    public function test_workflow_requires_an_independent_reviewer_and_records_history(): void
    {
        $author = $this->editor();
        $id = $this->postJson('/api/v1/admin/content/posts', $this->payload())->assertCreated()->json('data.id');
        $this->postJson("/api/v1/admin/content/posts/$id/transition", ['action' => 'publish'])->assertConflict();
        $this->postJson("/api/v1/admin/content/posts/$id/transition", ['action' => 'submit'])->assertOk();
        $this->postJson("/api/v1/admin/content/posts/$id/transition", ['action' => 'approve'])->assertConflict();
        $reviewer = $this->editor();
        $this->postJson("/api/v1/admin/content/posts/$id/transition", ['action' => 'approve', 'notes' => 'Reviewed'])->assertOk();
        $this->postJson("/api/v1/admin/content/posts/$id/transition", ['action' => 'publish'])->assertOk();
        $this->assertDatabaseHas('content_posts', ['id' => $id, 'status' => 'published', 'author_id' => $author->id, 'reviewer_id' => $reviewer->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'content.publish', 'entity_id' => $id]);
        $this->getJson('/api/v1/admin/content/posts/'.$id)->assertOk()->assertJsonCount(4, 'data.reviews');
    }

    public function test_parish_editors_cannot_approve_or_access_other_authors_drafts(): void
    {
        $this->editor('parish_admin');
        $other = ContentPost::factory()->create(['status' => 'in_review']);
        $this->getJson('/api/v1/admin/content/posts/'.$other->id)->assertForbidden();
        $this->postJson('/api/v1/admin/content/posts/'.$other->id.'/transition', ['action' => 'approve'])->assertForbidden();
        $this->editor('province_admin');
        $this->getJson('/api/v1/admin/content/posts')->assertForbidden();
        $this->postJson('/api/v1/admin/content/posts', $this->payload())->assertForbidden();
    }

    public function test_daily_reflections_are_selected_by_reflection_date_and_language_not_publish_date(): void
    {
        $post = ContentPost::factory()->published()->create(['content_type' => 'reflection']);
        $date = now('Africa/Dar_es_Salaam')->toDateString();
        DailyReflection::create(['content_post_id' => $post->id, 'locale' => 'sw', 'reflection_date' => $date]);
        $this->getJson('/api/v1/content/daily-reflection?date='.$date)->assertOk()->assertJsonPath('data.id', $post->id)->assertJsonPath('data.reflection_date', $date);
        $this->getJson('/api/v1/content/daily-reflection?locale=en')->assertOk()->assertJsonPath('data', null);
        $this->editor();
        $payload = $this->payload('duplicate-daily'); $payload['content_type'] = 'reflection';
        $payload['reflections'] = [['locale' => 'sw', 'reflection_date' => $date]];
        $this->postJson('/api/v1/admin/content/posts', $payload)->assertConflict();
        $this->assertDatabaseCount('daily_reflections', 1);
    }

    public function test_bookmarks_are_private_idempotent_and_hide_withdrawn_content(): void
    {
        $post = ContentPost::factory()->published()->create();
        $this->postJson('/api/v1/me/bookmarks', ['post_id' => $post->id])->assertUnauthorized();
        $member = Member::factory()->create();
        $user = User::factory()->create(['member_id' => $member->id, 'role_id' => null]);
        Sanctum::actingAs($user, ['member:read']);
        $this->postJson('/api/v1/me/bookmarks', ['post_id' => $post->id])->assertOk();
        $this->postJson('/api/v1/me/bookmarks', ['post_id' => $post->id])->assertOk();
        $this->assertDatabaseCount('content_bookmarks', 1);
        $this->getJson('/api/v1/me/bookmarks')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/me/bookmarks/'.$post->id)->assertJsonPath('data.bookmarked', true);
        $post->status = 'archived'; $post->save();
        $this->getJson('/api/v1/me/bookmarks')->assertJsonCount(0, 'data');
        $this->deleteJson('/api/v1/me/bookmarks/'.$post->id)->assertOk();
        $this->assertDatabaseCount('content_bookmarks', 0);
    }

    public function test_scheduling_waits_until_due_and_unpublishing_invalidates_cached_feeds(): void
    {
        $actor = $this->editor();
        $post = ContentPost::factory()->create(['status' => 'approved', 'reviewer_id' => $actor->id]);
        $this->postJson('/api/v1/admin/content/posts/'.$post->id.'/transition', ['action' => 'schedule', 'scheduled_at' => now()->addHour()->toIso8601String()])->assertOk();
        $this->getJson('/api/v1/content/feed')->assertJsonCount(0, 'data');
        app(ContentPublishing::class)->publishDue();
        $this->assertSame('approved', $post->fresh()->status);
        $this->travel(61)->minutes();
        app(ContentPublishing::class)->publishDue();
        $this->getJson('/api/v1/content/feed')->assertJsonCount(1, 'data');
        $revision = Cache::get('content:revision');
        $this->postJson('/api/v1/admin/content/posts/'.$post->id.'/transition', ['action' => 'unpublish'])->assertOk();
        $this->assertNotSame($revision, Cache::get('content:revision'));
        $this->getJson('/api/v1/content/feed')->assertJsonCount(0, 'data');
    }

    public function test_media_validation_rights_and_private_storage_are_enforced(): void
    {
        Storage::fake('local'); $this->editor();
        $this->post('/api/v1/admin/content/media', ['file' => UploadedFile::fake()->create('bad.svg', 1, 'image/svg+xml'), 'source_name' => 'Test', 'attribution' => 'Test', 'rights_status' => 'original'], ['Accept' => 'application/json'])->assertUnprocessable();
        $response = $this->post('/api/v1/admin/content/media', ['file' => UploadedFile::fake()->create('sound.mp3', 20, 'audio/mpeg'), 'source_name' => 'Test recording', 'attribution' => 'Test creator', 'rights_status' => 'unknown'], ['Accept' => 'application/json'])->assertCreated()->assertJsonMissingPath('data.storage_path');
        $media = ContentMedia::findOrFail($response->json('data.id'));
        $this->get('/api/v1/content/media/'.$media->id)->assertNotFound();
        $this->get('/api/v1/admin/content/media/'.$media->id.'/preview')->assertOk();
        $this->editor('parish_admin');
        $this->get('/api/v1/admin/content/media/'.$media->id.'/preview')->assertForbidden();
        $this->editor();
        $post = ContentPost::factory()->create(['status' => 'in_review', 'audio_media_id' => $media->id, 'format' => 'audio']);
        $this->postJson('/api/v1/admin/content/posts/'.$post->id.'/transition', ['action' => 'approve'])->assertUnprocessable();
    }

    public function test_editing_published_content_removes_approval_until_it_is_reviewed_again(): void
    {
        $this->editor(); $post = ContentPost::factory()->published()->create();
        $this->getJson('/api/v1/content/feed')->assertJsonCount(1, 'data');
        $this->patchJson('/api/v1/admin/content/posts/'.$post->id, $this->payload($post->slug))->assertOk();
        $this->assertDatabaseHas('content_posts', ['id' => $post->id, 'status' => 'draft', 'reviewer_id' => null]);
        $this->getJson('/api/v1/content/feed')->assertJsonCount(0, 'data');
    }

    public function test_read_only_tokens_cannot_modify_editorial_content(): void
    {
        $user = $this->editor();
        Sanctum::actingAs($user, ['directory:read']);
        $this->getJson('/api/v1/admin/content/posts')->assertOk();
        $this->postJson('/api/v1/admin/content/posts', $this->payload())->assertForbidden();
    }

    public function test_searching_tags_and_body_never_returns_private_drafts(): void
    {
        $post = ContentPost::factory()->published()->create();
        $draft = ContentPost::factory()->create();
        $tag = \App\Models\ContentTag::create(['name' => 'Unique search needle', 'slug' => 'unique-needle']);
        foreach ([$post, $draft] as $record) {
            $record->tags()->attach($tag);
            $record->translations()->update(['body' => 'Only testing body-needle content.']);
        }
        foreach (['Unique search', 'body-needle'] as $q) {
            $this->getJson('/api/v1/content/search?q='.urlencode($q))->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $post->id);
        }
    }

    public function test_reporting_and_category_withdrawal_are_reviewed_and_audited(): void
    {
        $post = ContentPost::factory()->published()->create();
        $this->postJson('/api/v1/content/posts/'.$post->slug.'/reports', ['reason' => 'Please review this test content.'])->assertCreated();
        $this->editor();
        $report = $this->getJson('/api/v1/admin/content/reports')->assertOk()->json('data.0.id');
        $this->patchJson('/api/v1/admin/content/reports/'.$report)->assertOk();
        $this->assertDatabaseHas('audit_logs', ['action' => 'content.report_resolved', 'entity_id' => $report]);
        $this->getJson('/api/v1/content/feed')->assertJsonCount(1, 'data');
        $category = $post->categories->first();
        $this->patchJson('/api/v1/admin/content/taxonomy/categories/'.$category->id, [
            'name' => $category->name, 'slug' => $category->slug, 'icon' => $category->icon, 'sort_order' => 0, 'is_active' => false,
        ])->assertOk();
        $this->getJson('/api/v1/content/feed')->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/content/posts/'.$post->slug)->assertNotFound();
    }

    public function test_member_bookmarks_are_not_visible_to_another_member(): void
    {
        $post = ContentPost::factory()->published()->create();
        $owner = User::factory()->create(['member_id' => Member::factory(), 'role_id' => null]);
        ContentBookmark::create(['user_id' => $owner->id, 'content_post_id' => $post->id]);
        $other = User::factory()->create(['member_id' => Member::factory(), 'role_id' => null]);
        Sanctum::actingAs($other, ['member:read']);
        $this->getJson('/api/v1/me/bookmarks')->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/me/bookmarks/'.$post->id)->assertJsonPath('data.bookmarked', false);
        $this->deleteJson('/api/v1/me/bookmarks/'.$post->id)->assertOk();
        $this->assertDatabaseHas('content_bookmarks', ['user_id' => $owner->id, 'content_post_id' => $post->id]);
    }

    public function test_public_reading_logs_do_not_record_user_or_ip_identity(): void
    {
        $post = ContentPost::factory()->published()->create();
        \Illuminate\Support\Facades\Log::spy();
        $this->getJson('/api/v1/content/posts/'.$post->slug)->assertOk();
        \Illuminate\Support\Facades\Log::shouldHaveReceived('info')->with('api.request', \Mockery::on(fn (array $context): bool =>
            $context['user_id'] === null && $context['ip_address'] === null && $context['endpoint'] === 'api/v1/content/posts/{slug}'
        ));
    }

    public function test_active_portal_accounts_can_save_their_own_public_bookmarks(): void
    {
        $editor = $this->editor();
        $post = ContentPost::factory()->published()->create();
        $this->postJson('/api/v1/me/bookmarks', ['post_id' => $post->id])->assertOk();
        $this->assertDatabaseHas('content_bookmarks', ['user_id' => $editor->id, 'content_post_id' => $post->id]);
        $this->getJson('/api/v1/me/bookmarks')->assertJsonCount(1, 'data');
    }
}
