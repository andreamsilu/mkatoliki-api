<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MemberServiceRequestResource extends JsonResource
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
            'sacrament_id' => $this->member_sacrament_id,
            'type' => $this->type,
            'message' => $this->message,
            'status' => $this->status,
            'requested_at' => $this->requested_at?->toIso8601String(),
            'reviewed_at' => $this->reviewed_at?->toIso8601String(),
            'member' => $this->whenLoaded('member', fn (): array => [
                'id' => $this->member->id,
                'code' => $this->member->member_code,
                'name' => collect([$this->member->first_name, $this->member->middle_name, $this->member->last_name])->filter()->join(' '),
            ]),
            'sacrament' => $this->whenLoaded('sacrament', fn (): ?array => $this->sacrament ? [
                'id' => $this->sacrament->id,
                'name' => $this->sacrament->name,
            ] : null),
        ];
    }
}
