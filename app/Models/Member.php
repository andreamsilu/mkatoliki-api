<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['parish_id', 'family_id', 'family_relationship', 'outstation_id', 'zone_id', 'jumuiya_id', 'member_code', 'first_name', 'middle_name', 'last_name', 'gender', 'date_of_birth', 'membership_started_at', 'phone', 'email', 'status'])]
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

    public function contributionPayments(): HasMany
    {
        return $this->hasMany(ContributionPayment::class);
    }

    public function sacraments(): HasMany
    {
        return $this->hasMany(MemberSacrament::class);
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(MemberNotification::class);
    }

    public function serviceRequests(): HasMany
    {
        return $this->hasMany(MemberServiceRequest::class);
    }

    public function user(): HasOne
    {
        return $this->hasOne(User::class);
    }
}
