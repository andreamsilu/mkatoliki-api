<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['parish_id', 'outstation_id', 'name', 'code', 'description', 'status', 'source_id', 'verification_status', 'verified_at'])]
class Choir extends DirectoryEntity
{
    protected $table = 'choirs';

    public function parish(): BelongsTo
    {
        return $this->belongsTo(Parish::class, 'parish_id');
    }

    public function outstation(): BelongsTo
    {
        return $this->belongsTo(Outstation::class, 'outstation_id');
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(DataSource::class, 'source_id');
    }
}
