<?php

namespace App\Http\Resources;

use App\Models\Parish;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Parish */
class ParishContentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'location' => $this->address ?: $this->deanery?->name,
            'phone' => $this->phone,
            'email' => $this->email,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'mass_times' => $this->massTimes->map(fn ($massTime): array => [
                'id' => $massTime->id,
                'day' => $massTime->day_label,
                'times' => $massTime->time_label,
            ]),
            'announcements' => $this->announcements->map(fn ($announcement): array => [
                'id' => $announcement->id,
                'title' => $announcement->title,
                'summary' => $announcement->summary,
                'category' => $announcement->category,
                'published_at' => $announcement->published_at?->toIso8601String(),
            ]),
            'events' => $this->events->map(fn ($event): array => [
                'id' => $event->id,
                'title' => $event->title,
                'starts_at' => $event->starts_at->toIso8601String(),
                'ends_at' => $event->ends_at?->toIso8601String(),
                'venue' => $event->venue,
                'description' => $event->description,
            ]),
            'projects' => $this->projects->map(fn ($project): array => [
                'id' => $project->id,
                'title' => $project->title,
                'subtitle' => $project->subtitle,
                'progress' => $project->progress_percentage / 100,
            ]),
        ];
    }
}
