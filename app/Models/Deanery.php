<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['diocese_id', 'code', 'name', 'name_en', 'status', 'source_id', 'verification_status', 'verified_at'])]
class Deanery extends DirectoryEntity
{
    protected $table = 'deaneries';

    public function diocese(): BelongsTo
    {
        return $this->belongsTo(Diocese::class, 'diocese_id');
    }

    public function parishes(): HasMany
    {
        return $this->hasMany(Parish::class, 'deanery_id');
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(DataSource::class, 'source_id');
    }
}
