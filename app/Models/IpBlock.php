<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IpBlock extends Model
{
    protected $fillable = ['ip', 'reason', 'automatic', 'expires_at', 'created_by', 'hits', 'last_hit_at'];

    protected $casts = [
        'automatic' => 'boolean',
        'expires_at' => 'datetime',
        'last_hit_at' => 'datetime',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }
}
