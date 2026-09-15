<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['parish_id', 'family_id', 'outstation_id', 'zone_id', 'jumuiya_id', 'member_code', 'first_name', 'middle_name', 'last_name', 'gender', 'date_of_birth', 'phone', 'email', 'status'])]
class Member extends DirectoryEntity
{
    protected $table = 'members';

    public function parish(): BelongsTo
    {
        return $this->belongsTo(Parish::class, 'parish_id');
    }

    public function family(): BelongsTo
    {
        return $this->belongsTo(Family::class, 'family_id');
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
}
