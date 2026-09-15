<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code', 'name', 'name_en', 'description', 'status', 'source_id', 'verification_status', 'verified_at'])]
class EcclesiasticalProvince extends DirectoryEntity
{
    protected $table = 'ecclesiastical_provinces';

    public function dioceses(): HasMany
    {
        return $this->hasMany(Diocese::class, 'ecclesiastical_province_id');
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(DataSource::class, 'source_id');
    }
}
