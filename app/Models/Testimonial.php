<?php

namespace App\Models;

use App\Enums\CommonStatusEnum;
use App\Traits\Trackable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Testimonial extends Model
{
    use SoftDeletes, Trackable;

    public const RATINGS = [
        5 => 'Excellent',
        4 => 'Very good',
        3 => 'Good',
        2 => 'Fair',
        1 => 'Poor',
    ];

    protected $fillable = [
        'name',
        'designation',
        'company',
        'quote',
        'photo',
        'rating',
        'is_featured',
        'sort_order',
        'status',
    ];

    protected $casts = [
        'status' => CommonStatusEnum::class,
        'rating' => 'integer',
        'is_featured' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', CommonStatusEnum::ACTIVE->value);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderByDesc('is_featured')->orderBy('sort_order')->latest();
    }

    public function getPhotoUrlAttribute(): ?string
    {
        return media_url($this->photo);
    }

    public function getByLineAttribute(): ?string
    {
        return collect([$this->designation, $this->company])->filter()->implode(', ') ?: null;
    }
}
