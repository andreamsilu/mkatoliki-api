<?php

namespace App\Models;

use Database\Factories\MemberNotificationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['member_id', 'title', 'message', 'type', 'published_at', 'read_at'])]
class MemberNotification extends Model
{
    /** @use HasFactory<MemberNotificationFactory> */
    use HasFactory;

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    protected function casts(): array
    {
        return ['published_at' => 'datetime', 'read_at' => 'datetime'];
    }
}
