<?php

namespace App\Models;

use App\Enums\CommonStatusEnum;
use App\Traits\Trackable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasFactory, HasRoles, Notifiable, SoftDeletes, Trackable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'status',
        'bio',
        'avatar',
        'social_links',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'status' => CommonStatusEnum::class,
            'social_links' => 'array',
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'encrypted:array',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    public function hasTwoFactor(): bool
    {
        return $this->two_factor_confirmed_at !== null && filled($this->two_factor_secret);
    }

    public function requiresTwoFactor(): bool
    {
        return $this->roles->contains(fn ($role) => (bool) $role->requires_two_factor);
    }

    public function getAvatarUrlAttribute()
    {
        return $this->avatar
            ? media_url($this->avatar)
            : 'https://ui-avatars.com/api/?name='.urlencode($this->name);
    }

    public function blogs()
    {
        return $this->hasMany(Blog::class);
    }
}
