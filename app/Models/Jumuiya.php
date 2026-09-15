<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['parish_id', 'zone_id', 'outstation_id', 'code', 'name', 'name_en', 'description', 'status', 'source_id', 'verification_status', 'verified_at'])]
class Jumuiya extends DirectoryEntity
{
    protected $table = 'jumuiyas';

    public function parish(): BelongsTo
    {
        return $this->belongsTo(Parish::class, 'parish_id');
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class, 'zone_id');
    }

    public function outstation(): BelongsTo
    {
        return $this->belongsTo(Outstation::class, 'outstation_id');
    }

    public function families(): HasMany
    {
        return $this->hasMany(Family::class, 'jumuiya_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(Member::class, 'jumuiya_id');
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(DataSource::class, 'source_id');
    }
}
