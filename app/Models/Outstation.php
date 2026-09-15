<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['parish_id', 'code', 'name', 'name_en', 'address', 'latitude', 'longitude', 'status', 'source_id', 'verification_status', 'verified_at'])]
class Outstation extends DirectoryEntity
{
    protected $table = 'outstations';

    public function parish(): BelongsTo
    {
        return $this->belongsTo(Parish::class, 'parish_id');
    }

    public function zones(): HasMany
    {
        return $this->hasMany(Zone::class, 'outstation_id');
    }

    public function jumuiyas(): HasMany
    {
        return $this->hasMany(Jumuiya::class, 'outstation_id');
    }

    public function families(): HasMany
    {
        return $this->hasMany(Family::class, 'outstation_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(Member::class, 'outstation_id');
    }

    public function associations(): HasMany
    {
        return $this->hasMany(Association::class, 'outstation_id');
    }

    public function choirs(): HasMany
    {
        return $this->hasMany(Choir::class, 'outstation_id');
    }

    public function ministries(): HasMany
    {
        return $this->hasMany(Ministry::class, 'outstation_id');
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(DataSource::class, 'source_id');
    }
}
