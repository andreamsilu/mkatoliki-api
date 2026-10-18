<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class CatholicDailyReadings
{
    public const TIMEZONE = 'Africa/Dar_es_Salaam';

    private const BOOKS = [
        'genesis', 'exodus', 'leviticus', 'numbers', 'deuteronomy', 'joshua',
        'judges', 'ruth', '1_samuel', '2_samuel', '1_kings', '2_kings',
        '1_chronicles', '2_chronicles', 'ezra', 'nehemiah', 'tobit', 'judith',
        'esther', '1_maccabees', '2_maccabees', 'job', 'psalm', 'proverbs',
        'ecclesiastes', 'song_of_solomon', 'wisdom', 'sirach', 'isaiah', 'jeremiah',
        'lamentations', 'baruch', 'ezekiel', 'daniel', 'hosea', 'joel', 'amos',
        'obadiah', 'jonah', 'micah', 'nahum', 'habakkuk', 'zephaniah', 'haggai',
        'zechariah', 'malachi', 'matthew', 'mark', 'luke', 'john', 'acts', 'romans',
        '1_corinthians', '2_corinthians', 'galatians', 'ephesians', 'philippians',
        'colossians', '1_thessalonians', '2_thessalonians', '1_timothy', '2_timothy',
        'titus', 'philemon', 'hebrews', 'james', '1_peter', '2_peter', '1_john',
        '2_john', '3_john', 'jude', 'revelation',
    ];

    /** @return array<string, mixed>|null */
    public function today(): ?array
    {
        $today = CarbonImmutable::now(self::TIMEZONE);
        $date = $today->toDateString();
        $origins = array_values(array_unique(array_filter([
            config('services.catholic_readings.base_url'),
            config('services.catholic_readings.fallback_url'),
        ])));
        $key = 'catholic-daily-readings:v1:'.$date.':'.sha1(implode('|', $origins));
        $cache = Cache::store(config('services.catholic_readings.cache_store', 'file'));

        try {
            $cached = $cache->get($key);
            if (is_array($cached)) {
                return $cached['data'];
            }
        } catch (Throwable) {
            // Readings remain available if the cache store is temporarily unavailable.
        }

        foreach ($origins as $origin) {
            try {
                $response = Http::acceptJson()->withoutRedirecting()
                    ->connectTimeout(2)->timeout(4)
                    ->get(rtrim($origin, '/').'/readings/'.$today->format('Y/m-d').'.json');
                if (! $response->successful()) {
                    continue;
                }
                $data = $this->normalize($response->json(), $today);
                if ($data === null) {
                    continue;
                }
                try {
                    $cache->put($key, ['data' => $data], $today->endOfDay()->addHour());
                } catch (Throwable) {
                    // A successful provider response does not depend on caching.
                }

                return $data;
            } catch (Throwable) {
                continue;
            }
        }

        Log::warning('catholic_readings.unavailable', ['date' => $date]);
        try {
            $cache->put($key, ['data' => null], now()->addMinutes(5));
        } catch (Throwable) {
            // Cache failure must not change the public error response.
        }

        return null;
    }

    /** @return array<string, mixed>|null */
    private function normalize(mixed $payload, CarbonImmutable $day): ?array
    {
        if (! is_array($payload) || ($payload['date'] ?? null) !== $day->toDateString()
            || ! is_array($payload['readings'] ?? null)) {
            return null;
        }
        $readings = [];
        foreach (['firstReading' => 'first_reading', 'psalm' => 'psalm', 'secondReading' => 'second_reading', 'gospel' => 'gospel'] as $field => $type) {
            $reference = $payload['readings'][$field] ?? null;
            if ($field === 'secondReading' && ($reference === null || $reference === '')) {
                continue;
            }
            if (! is_string($reference) || mb_strlen($reference) > 500) {
                return null;
            }
            $reference = preg_replace('/[\s\p{Z}]+/u', ' ', trim($reference));
            if ($reference === null || ! preg_match('/^(.+?)\s+(\d.*)$/u', $reference, $parts)) {
                return null;
            }
            $book = strtolower(str_replace(' ', '_', $parts[1]));
            $book = match ($book) {
                'psalms' => 'psalm',
                'acts_of_the_apostles' => 'acts',
                'ecclesiasticus' => 'sirach',
                'song_of_songs', 'canticles' => 'song_of_solomon',
                default => $book,
            };
            if (! in_array($book, self::BOOKS, true)) {
                return null;
            }
            $readings[] = ['type' => $type, 'book' => $book, 'verses' => $parts[2]];
        }

        return [
            'date' => $day->toDateString(),
            'timezone' => self::TIMEZONE,
            'source' => 'Catholic Readings API',
            'source_url' => 'https://github.com/cpbjr/catholic-readings-api',
            'calendar' => 'USCCB',
            'references_only' => true,
            'readings' => $readings,
            'full_readings_url' => 'https://bible.usccb.org/bible/readings/'.$day->format('mdy').'.cfm',
            'full_readings_language' => 'en',
        ];
    }
}
