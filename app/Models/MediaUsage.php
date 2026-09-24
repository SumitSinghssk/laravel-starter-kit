<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MediaUsage extends Model
{
    protected $fillable = [
        'media_file_id',
        'source',
        'title',
        'detail',
        'url',
    ];
}
