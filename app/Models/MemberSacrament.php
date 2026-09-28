<?php

namespace App\Models;

use Database\Factories\MemberSacramentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['member_id', 'name', 'received_on', 'place', 'status'])]
class MemberSacrament extends Model
{
    /** @use HasFactory<MemberSacramentFactory> */
    use HasFactory;

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function serviceRequests(): HasMany
    {
        return $this->hasMany(MemberServiceRequest::class);
    }

    protected function casts(): array
    {
        return ['received_on' => 'date:Y-m-d'];
    }
}
