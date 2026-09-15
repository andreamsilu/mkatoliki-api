<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['parish_id', 'outstation_id', 'zone_id', 'jumuiya_id', 'family_code', 'family_name', 'address', 'phone', 'status'])]
class Family extends DirectoryEntity
{
    protected $table = 'families';

    public function parish(): BelongsTo
    {
        return $this->belongsTo(Parish::class, 'parish_id');
    }

    public function outstation(): BelongsTo
    {
        return $this->belongsTo(Outstation::class, 'outstation_id');
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class, 'zone_id');
    }

    public function jumuiya(): BelongsTo
    {
        return $this->belongsTo(Jumuiya::class, 'jumuiya_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(Member::class, 'family_id');
    }
}
