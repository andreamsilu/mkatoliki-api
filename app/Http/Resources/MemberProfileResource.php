<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MemberProfileResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $name = collect([$this->first_name, $this->middle_name, $this->last_name])
            ->filter()
            ->implode(' ');

        return [
            'id' => $this->id,
            'member_code' => $this->member_code,
            'name' => $name,
            'email' => $this->email ?? $this->user?->email ?? $request->user()?->email,
            'phone' => $this->phone,
            'parish' => $this->whenLoaded('parish', fn () => [
                'id' => $this->parish->id,
                'name' => $this->parish->name,
                'deanery' => $this->parish->deanery?->name,
            ]),
            'jumuiya' => $this->whenLoaded('jumuiya', fn () => $this->jumuiya ? [
                'id' => $this->jumuiya->id,
                'name' => $this->jumuiya->name,
                'description' => $this->jumuiya->description,
                'phone' => $this->jumuiya->phone,
                'leader' => $this->jumuiya->leader ? [
                    'name' => collect([
                        $this->jumuiya->leader->first_name,
                        $this->jumuiya->leader->middle_name,
                        $this->jumuiya->leader->last_name,
                    ])->filter()->implode(' '),
                    'phone' => $this->jumuiya->leader->phone,
                ] : null,
            ] : null),
        ];
    }
}
