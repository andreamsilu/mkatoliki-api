<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['source_id', 'created_by', 'reviewed_by', 'entity_type', 'checksum', 'status', 'rows', 'report', 'reviewed_at'])]
class ImportBatch extends Model
{
    use HasFactory;



    protected function casts(): array
    {
        return ['rows' => 'array', 'report' => 'array', 'reviewed_at' => 'datetime'];
    }
}
