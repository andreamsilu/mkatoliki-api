<?php

namespace App\Models;

use Database\Factories\ContributionPaymentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['member_id', 'contribution_campaign_id', 'amount', 'payment_method', 'reference', 'status', 'gateway_reference', 'receipt_number', 'requested_at', 'confirmed_at'])]
class ContributionPayment extends Model
{
    /** @use HasFactory<ContributionPaymentFactory> */
    use HasFactory;

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(ContributionCampaign::class, 'contribution_campaign_id');
    }

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'requested_at' => 'datetime', 'confirmed_at' => 'datetime'];
    }
}
