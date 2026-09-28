<?php

namespace App\Models;

use Database\Factories\MemberServiceRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['member_id', 'member_sacrament_id', 'type', 'message', 'status', 'requested_at', 'reviewed_at'])]
class MemberServiceRequest extends Model
{
    /** @use HasFactory<MemberServiceRequestFactory> */
    use HasFactory;

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function sacrament(): BelongsTo
    {
        return $this->belongsTo(MemberSacrament::class, 'member_sacrament_id');
    }

    protected function casts(): array
    {
        return ['requested_at' => 'datetime', 'reviewed_at' => 'datetime'];
    }
}
