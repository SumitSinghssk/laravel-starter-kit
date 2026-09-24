<?php

namespace App\Models;

use App\Traits\Trackable;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    use Trackable;

    protected $fillable = [
        'key',
        'value',
    ];

    protected $casts = [
        'value' => 'array',
    ];
}
