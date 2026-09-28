<?php

namespace App\Models;

use Database\Factories\ParishEventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['parish_id', 'title', 'starts_at', 'ends_at', 'venue', 'description', 'is_published'])]
class ParishEvent extends Model
{
    /** @use HasFactory<ParishEventFactory> */
    use HasFactory;

    public function parish(): BelongsTo
    {
        return $this->belongsTo(Parish::class);
    }

    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'ends_at' => 'datetime', 'is_published' => 'boolean'];
    }
}
