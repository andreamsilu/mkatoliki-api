<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MemberNotificationResource extends JsonResource
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
            'member_id' => $this->member_id,
            'title' => $this->title,
            'message' => $this->message,
            'type' => $this->type,
            'published_at' => $this->published_at?->toIso8601String(),
            'read_at' => $this->read_at?->toIso8601String(),
            'member' => $this->whenLoaded('member', fn (): array => [
                'id' => $this->member->id,
                'code' => $this->member->member_code,
                'name' => collect([$this->member->first_name, $this->member->middle_name, $this->member->last_name])->filter()->join(' '),
            ]),
        ];
    }
}
