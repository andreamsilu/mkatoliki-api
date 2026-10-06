<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MemberSacramentResource extends JsonResource
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
            'name' => $this->name,
            'received_on' => $this->received_on?->toDateString(),
            'place' => $this->place,
            'status' => $this->status,
            'member' => $this->whenLoaded('member', fn (): array => [
                'id' => $this->member->id,
                'code' => $this->member->member_code,
                'name' => collect([$this->member->first_name, $this->member->middle_name, $this->member->last_name])->filter()->join(' '),
            ]),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
