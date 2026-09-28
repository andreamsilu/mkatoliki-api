<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['deanery_id', 'code', 'name', 'name_en', 'patron_saint', 'established_at', 'address', 'phone', 'email', 'latitude', 'longitude', 'status', 'source_id', 'verification_status', 'verified_at'])]
class Parish extends DirectoryEntity
{
    use SoftDeletes;

    protected $table = 'parishes';

    public function deanery(): BelongsTo
    {
        return $this->belongsTo(Deanery::class, 'deanery_id');
    }

    public function outstations(): HasMany
    {
        return $this->hasMany(Outstation::class, 'parish_id');
    }

    public function zones(): HasMany
    {
        return $this->hasMany(Zone::class, 'parish_id');
    }

    public function jumuiyas(): HasMany
    {
        return $this->hasMany(Jumuiya::class, 'parish_id');
    }

    public function families(): HasMany
    {
        return $this->hasMany(Family::class, 'parish_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(Member::class, 'parish_id');
    }

    public function contributionCampaigns(): HasMany
    {
        return $this->hasMany(ContributionCampaign::class);
    }

    public function massTimes(): HasMany
    {
        return $this->hasMany(ParishMassTime::class);
    }

    public function announcements(): HasMany
    {
        return $this->hasMany(ParishAnnouncement::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(ParishEvent::class);
    }

    public function projects(): HasMany
    {
        return $this->hasMany(ParishProject::class);
    }

    public function associations(): HasMany
    {
        return $this->hasMany(Association::class, 'parish_id');
    }

    public function choirs(): HasMany
    {
        return $this->hasMany(Choir::class, 'parish_id');
    }

    public function ministries(): HasMany
    {
        return $this->hasMany(Ministry::class, 'parish_id');
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(DataSource::class, 'source_id');
    }
}
