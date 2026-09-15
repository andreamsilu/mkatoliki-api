<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['user_id', 'action', 'entity_type', 'entity_id', 'old_values', 'new_values', 'ip_address', 'user_agent', 'request_id'])]
class AuditLog extends Model
{
    use HasFactory;

    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return ['old_values' => 'array', 'new_values' => 'array'];
    }
}
