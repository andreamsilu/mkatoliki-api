<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

abstract class DirectoryEntity extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'established_at' => 'date:Y-m-d',
            'date_of_birth' => 'date:Y-m-d',
            'verified_at' => 'datetime',
            'latitude' => 'float',
            'longitude' => 'float',
        ];
    }
}

