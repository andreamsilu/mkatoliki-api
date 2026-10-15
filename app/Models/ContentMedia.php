<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContentMedia extends Model
{
    protected $table = 'content_media';
    protected $guarded = ['id'];
    protected $hidden = ['storage_path'];
}
