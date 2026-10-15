<?php

namespace App\Http\Resources;

use App\Models\ContentMedia;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ContentPostResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $translation = $this->translations->firstWhere('locale', $request->query('locale', 'sw'));
        $detail = $request->attributes->get('content_detail', false);
        return [
            'id' => $this->id, 'slug' => $this->slug, 'content_type' => $this->content_type,
            'format' => $this->format, 'featured' => $this->featured, 'season' => $this->season,
            'share_url' => config('content.web_url') ? rtrim(config('content.web_url'), '/').'/faith/'.$this->slug.'?locale='.$request->query('locale', 'sw') : null,
            'locale' => $translation?->locale, 'title' => $translation?->title, 'summary' => $translation?->summary,
            'reflection_date' => $this->reflections->firstWhere('locale', $request->query('locale', 'sw'))?->reflection_date,
            'body' => $this->when($request->attributes->get('content_detail', false), fn () => $translation?->body),
            'scripture_reference' => $translation?->scripture_reference,
            'practical_action' => $this->when($detail, fn () => $translation?->practical_action), 'closing_prayer' => $this->when($detail, fn () => $translation?->closing_prayer),
            'author' => $this->author?->name, 'published_at' => $this->published_at?->toIso8601String(),
            'reading_time_minutes' => $this->reading_time_minutes,
            'source_type' => $this->source_type, 'source_name' => $this->source_name, 'source_url' => $this->source_url,
            'rights_status' => $this->rights_status, 'license_details' => $this->when($detail, $this->license_details),
            'composer' => $this->composer, 'choir' => $this->choir,
            'categories' => $this->categories->map->only(['id', 'name', 'slug', 'icon'])->values(),
            'tags' => $this->tags->map->only(['id', 'name', 'slug'])->values(),
            'cover' => $this->asset($this->cover, $detail), 'audio' => $this->asset($this->audio, $detail),
        ];
    }

    private function asset(?ContentMedia $asset, bool $detail): ?array
    {
        if (! $asset || $asset->rights_status === 'unknown') {
            return null;
        }
        $fields = ['id', 'alt_text', 'attribution', 'source_name', 'source_url', 'rights_status', 'duration_seconds'];
        if ($detail) { $fields[] = 'license_details'; }
        return $asset->only($fields) + ['url' => rtrim(config('app.url'), '/').'/api/v1/content/media/'.$asset->id];
    }
}
