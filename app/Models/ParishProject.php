<?php

namespace App\Models;

use Database\Factories\ParishProjectFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['parish_id', 'title', 'subtitle', 'progress_percentage', 'is_published'])]
class ParishProject extends Model
{
    /** @use HasFactory<ParishProjectFactory> */
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
