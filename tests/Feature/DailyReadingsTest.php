<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DailyReadingsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'cache.default' => 'array',
            'services.catholic_readings.cache_store' => 'array',
            'services.catholic_readings.base_url' => 'https://cpbjr.github.io/catholic-readings-api',
            'services.catholic_readings.fallback_url' => 'https://raw.githubusercontent.com/cpbjr/catholic-readings-api/main',
        ]);
        Http::preventStrayRequests();
        $this->travelTo(now()->setTimezone('UTC')->setDate(2026, 10, 16)->setTime(22, 0));
    }

    /** @return array<string, mixed> */
    private function payload(string $date = '2026-10-17'): array
    {
        return [
            'date' => $date,
            'readings' => [
                'firstReading' => 'Ephesians 1:15-23',
                'psalm' => "Psalm\u{00A0}8:2-3ab, 4-5, 6-7",
                'gospel' => 'Luke 12:8-12',
            ],
            'usccbLink' => 'https://invalid.example/untrusted-link',
        ];
    }

    public function test_public_readings_use_tanzania_date_and_share_a_cache_without_editorial_posts(): void
    {
        Http::fake(['*/readings/2026/10-17.json' => Http::response($this->payload())]);
        $this->getJson('/api/v1/content/daily-readings')->assertOk()
            ->assertJsonPath('data.date', '2026-10-17')
            ->assertJsonPath('data.timezone', 'Africa/Dar_es_Salaam')
            ->assertJsonPath('data.references_only', true)
            ->assertJsonPath('data.readings.0.book', 'ephesians')
            ->assertJsonPath('data.readings.1.book', 'psalm')
            ->assertJsonPath('data.readings.1.verses', '8:2-3ab, 4-5, 6-7')
            ->assertJsonPath('data.readings.2.type', 'gospel')
            ->assertJsonCount(3, 'data.readings')
            ->assertJsonPath('data.full_readings_url', 'https://bible.usccb.org/bible/readings/101726.cfm');
        $this->getJson('/api/v1/content/daily-readings')->assertOk();
        Http::assertSentCount(1);
    }

    public function test_the_official_repository_mirror_is_used_when_pages_is_unavailable(): void
    {
        Http::fake([
            'https://cpbjr.github.io/*' => Http::response('', 503),
            'https://raw.githubusercontent.com/*' => Http::response($this->payload()),
        ]);
        $this->getJson('/api/v1/content/daily-readings')->assertOk();
        Http::assertSentCount(2);
    }

    public function test_optional_second_reading_is_preserved(): void
    {
        $payload = $this->payload();
        $payload['readings']['secondReading'] = '2 Timothy 3:14-4:2';
        Http::fake(['*' => Http::response($payload)]);
        $this->getJson('/api/v1/content/daily-readings')->assertOk()
            ->assertJsonCount(4, 'data.readings')
            ->assertJsonPath('data.readings.2.book', '2_timothy')
            ->assertJsonPath('data.readings.2.type', 'second_reading');
    }

    public function test_wrong_date_and_malformed_provider_data_are_never_shown_as_today(): void
    {
        Http::fake([
            'https://cpbjr.github.io/*' => Http::response($this->payload('2026-10-16')),
            'https://raw.githubusercontent.com/*' => Http::response(['date' => '2026-10-17', 'readings' => []]),
        ]);
        $this->getJson('/api/v1/content/daily-readings')->assertStatus(503)
            ->assertJsonPath('error.code', 'DAILY_READINGS_UNAVAILABLE');
        $this->getJson('/api/v1/content/daily-readings')->assertStatus(503);
        Http::assertSentCount(2);
    }

    public function test_a_new_tanzania_day_never_reuses_the_previous_days_cache(): void
    {
        Http::fake(['*' => Http::response($this->payload())]);
        $this->getJson('/api/v1/content/daily-readings')->assertOk();
        $this->travelTo(now()->setTimezone('UTC')->setDate(2026, 10, 17)->setTime(21, 1));
        $this->getJson('/api/v1/content/daily-readings')->assertStatus(503);
        Http::assertSentCount(3);
    }

    public function test_an_unknown_scripture_book_is_not_cached_as_valid_data(): void
    {
        $payload = $this->payload();
        $payload['readings']['firstReading'] = 'Unknown Book 1:1-3';
        Http::fake(['*' => Http::response($payload)]);
        $this->getJson('/api/v1/content/daily-readings')->assertStatus(503)
            ->assertJsonPath('error.code', 'DAILY_READINGS_UNAVAILABLE');
    }
}
