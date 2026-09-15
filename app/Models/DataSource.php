<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'type', 'publisher', 'reference', 'version', 'publication_date', 'description'])]
class DataSource extends Model
{
    use HasFactory;



    protected function casts(): array
    {
        return ['publication_date' => 'date:Y-m-d'];
    }
}
