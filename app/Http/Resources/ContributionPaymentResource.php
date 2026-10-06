<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ContributionPaymentResource extends JsonResource
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
            'campaign_id' => $this->contribution_campaign_id,
            'amount' => (float) $this->amount,
            'payment_method' => $this->payment_method,
            'reference' => $this->reference,
            'status' => $this->status,
            'gateway_reference' => $this->gateway_reference,
            'receipt_number' => $this->receipt_number,
            'requested_at' => $this->requested_at?->toIso8601String(),
            'confirmed_at' => $this->confirmed_at?->toIso8601String(),
            'member' => $this->whenLoaded('member', fn (): array => [
                'id' => $this->member->id,
                'code' => $this->member->member_code,
                'name' => collect([$this->member->first_name, $this->member->middle_name, $this->member->last_name])->filter()->join(' '),
            ]),
            'campaign' => $this->whenLoaded('campaign', fn (): array => [
                'id' => $this->campaign->id,
                'title' => $this->campaign->title,
            ]),
        ];
    }
}
