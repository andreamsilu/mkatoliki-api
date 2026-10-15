<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['slug', 'content_type', 'format', 'featured', 'season', 'cover_media_id', 'audio_media_id', 'source_type', 'source_name', 'source_url', 'rights_status', 'license_details', 'composer', 'choir', 'reading_time_minutes'])]
class ContentPost extends Model
{
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return ['featured' => 'boolean', 'published_at' => 'datetime', 'scheduled_at' => 'datetime'];
    }

    public function scopeVisible(Builder $query): void
    {
        $query->where('status', 'published')->whereNotNull('reviewer_id')
            ->whereColumn('reviewer_id', '!=', 'author_id')->where('published_at', '<=', now())
            ->whereHas('categories', fn (Builder $category) => $category->where('is_active', true));
    }

    public function translations(): HasMany { return $this->hasMany(ContentTranslation::class); }
    public function categories(): BelongsToMany { return $this->belongsToMany(ContentCategory::class, 'content_post_categories'); }
    public function tags(): BelongsToMany { return $this->belongsToMany(ContentTag::class, 'content_post_tags'); }
    public function reviews(): HasMany { return $this->hasMany(ContentReview::class)->latest('id'); }
    public function reflections(): HasMany { return $this->hasMany(DailyReflection::class); }
    public function author(): BelongsTo { return $this->belongsTo(User::class, 'author_id')->select(['id', 'name']); }
    public function cover(): BelongsTo { return $this->belongsTo(ContentMedia::class, 'cover_media_id'); }
    public function audio(): BelongsTo { return $this->belongsTo(ContentMedia::class, 'audio_media_id'); }
}
