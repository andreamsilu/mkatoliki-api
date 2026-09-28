<?php

namespace App\Models;

use Database\Factories\ContributionCampaignFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['parish_id', 'title', 'description', 'target_amount', 'starts_on', 'ends_on', 'status'])]
class ContributionCampaign extends Model
{
    /** @use HasFactory<ContributionCampaignFactory> */
    use HasFactory;

    public function parish(): BelongsTo
    {
        return $this->belongsTo(Parish::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(ContributionPayment::class);
    }

    protected function casts(): array
    {
        return ['target_amount' => 'decimal:2', 'starts_on' => 'date:Y-m-d', 'ends_on' => 'date:Y-m-d'];
    }
}
