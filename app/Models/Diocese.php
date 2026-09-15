<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['ecclesiastical_province_id', 'code', 'name', 'name_en', 'type', 'established_at', 'status', 'source_id', 'verification_status', 'verified_at'])]
class Diocese extends DirectoryEntity
{
    protected $table = 'dioceses';

    public function province(): BelongsTo
    {
        return $this->belongsTo(EcclesiasticalProvince::class, 'ecclesiastical_province_id');
    }

    public function deaneries(): HasMany
    {
        return $this->hasMany(Deanery::class, 'diocese_id');
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(DataSource::class, 'source_id');
    }
}
