<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityLog extends Model
{
    protected $fillable = [
        'user_id',
        'action',
        'model_type',
        'model_id',
        'model_name',
        'old_values',
        'new_values',
        'ip_address',
        'url',
        'method',
        'description',
        'page_title',
        'user_agent',
        'browser',
        'browser_version',
        'platform',
        'device_type',
        'device',
        'country',
        'country_code',
        'region',
        'city',
        'latitude',
        'longitude',
        'timezone',
        'isp',
        'session_id',
        'login_at',
        'logout_at',
        'session_duration',
        'is_suspicious',
        'email',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'login_at' => 'datetime',
        'logout_at' => 'datetime',
        'is_suspicious' => 'boolean',
        'latitude' => 'float',
        'longitude' => 'float',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getCreatedAtFormattedAttribute(): string
    {
        return $this->created_at
            ? $this->created_at->format('d M Y, H:i:s')
            : '—';
    }

    public function getSessionDurationFormattedAttribute(): ?string
    {
        if (! $this->session_duration) {
            return null;
        }

        $seconds = (int) $this->session_duration;
        $h = intdiv($seconds, 3600);
        $m = intdiv($seconds % 3600, 60);
        $s = $seconds % 60;

        $parts = [];
        if ($h) {
            $parts[] = "{$h}h";
        }
        if ($m) {
            $parts[] = "{$m}m";
        }
        if ($s || empty($parts)) {
            $parts[] = "{$s}s";
        }

        return implode(' ', $parts);
    }
}
