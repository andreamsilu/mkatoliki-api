<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['title', 'summary', 'body', 'report_date', 'is_published', 'published_at', 'document_path', 'document_name', 'document_size'])]
class ParishPublishedReport extends Model
{
    protected $hidden = ['document_path'];

    protected function casts(): array
    {
        return ['is_published' => 'boolean', 'published_at' => 'datetime', 'report_date' => 'date:Y-m-d'];
    }

    public function parish(): BelongsTo
    {
        return $this->belongsTo(Parish::class);
    }

    public function scopePublished(Builder $query): void
    {
        $query->where('is_published', true)->whereNotNull('published_at')->where('published_at', '<=', now());
    }
}
