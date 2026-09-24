<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Enquiry extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'source',
        'source_url',
        'data',
        'status',
        'seen_at',
        'seen_by',
    ];

    protected $casts = [
        'data' => 'array',
        'seen_at' => 'datetime',
    ];

    public function seenBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seen_by');
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'new' => 'blue',
            'seen' => 'green',
            'pending' => 'yellow',
            'closed' => 'slate',
            default => 'slate',
        };
    }

    public function getIsUnseenAttribute(): bool
    {
        return is_null($this->seen_at);
    }
}
