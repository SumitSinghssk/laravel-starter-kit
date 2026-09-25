<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BlockedRequest extends Model
{
    public const UPDATED_AT = null;

    public const REASONS = [
        'ip_blocked' => 'Blocked IP',
        'rate_limited' => 'Too many requests',
        'login_throttled' => 'Too many sign-in attempts',
        'honeypot' => 'Bot trap filled in',
        'too_fast' => 'Form sent too fast',
        'captcha_failed' => 'CAPTCHA failed',
    ];

    protected $fillable = ['ip', 'reason', 'method', 'path', 'user_agent', 'created_at'];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function getReasonLabelAttribute(): string
    {
        return self::REASONS[$this->reason] ?? $this->reason;
    }
}
