<?php

namespace App\Models;

use Database\Factories\ParishMassTimeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['parish_id', 'day_label', 'time_label', 'display_order', 'is_published'])]
class ParishMassTime extends Model
{
    /** @use HasFactory<ParishMassTimeFactory> */
    use HasFactory;

    public function parish(): BelongsTo
    {
        return $this->belongsTo(Parish::class);
    }

    protected function casts(): array
    {
        return ['is_published' => 'boolean'];
    }
}
