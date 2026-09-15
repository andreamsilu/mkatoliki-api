<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['entity_type', 'entity_id', 'source_id', 'status', 'verified_by', 'verified_at', 'notes'])]
class VerificationRecord extends Model
{
    use HasFactory;



    protected function casts(): array
    {
        return ['verified_at' => 'datetime'];
    }
}
