<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    protected $fillable = [
        'type',
        'title',
        'message',
        'data',
        'url',
        'seen_at',
    ];

    protected $casts = [
        'data' => 'array',
        'seen_at' => 'datetime',
    ];

    public function isSeen(): bool
    {
        return ! is_null($this->seen_at);
    }
}
