<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['parish_id', 'outstation_id', 'code', 'name', 'name_en', 'description', 'phone', 'email', 'leader_member_id', 'status', 'source_id', 'verification_status', 'verified_at'])]
class Zone extends DirectoryEntity
{
    use SoftDeletes;

    protected $table = 'zones';

    public function parish(): BelongsTo
    {
        return $this->belongsTo(Parish::class, 'parish_id');
    }

    public function outstation(): BelongsTo
    {
        return $this->belongsTo(Outstation::class, 'outstation_id');
    }

    public function leader(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'leader_member_id');
    }

    public function jumuiyas(): HasMany
    {
        return $this->hasMany(Jumuiya::class, 'zone_id');
    }

    public function families(): HasMany
    {
        return $this->hasMany(Family::class, 'zone_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(Member::class, 'zone_id');
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(DataSource::class, 'source_id');
    }
}
