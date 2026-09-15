<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['parish_id', 'old_diocese_id', 'new_diocese_id', 'old_deanery_id', 'new_deanery_id', 'effective_date', 'reason', 'source_id'])]
class ParishHistory extends Model
{
    use HasFactory;

    protected $table = 'parish_history';

    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return ['effective_date' => 'date:Y-m-d'];
    }
}
