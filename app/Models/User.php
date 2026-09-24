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
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'status' => CommonStatusEnum::class,
            'social_links' => 'array',
        ];
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
