<?php

namespace App\Models;

use Database\Factories\ParishAnnouncementFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['parish_id', 'title', 'summary', 'category', 'published_at', 'is_published'])]
class ParishAnnouncement extends Model
{
    /** @use HasFactory<ParishAnnouncementFactory> */
    use HasFactory;

    public function parish(): BelongsTo
    {
        return $this->belongsTo(Parish::class);
    }

    protected function casts(): array
    {
        return ['published_at' => 'datetime', 'is_published' => 'boolean'];
    }
}
