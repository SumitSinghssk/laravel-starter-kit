<?php

namespace App\Models;

use App\Enums\CommonStatusEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Page extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'title',
        'slug',
        'content',
        'featured_image',
        'status',
        'published_at',
    ];

    protected $casts = [
        'published_at' => 'datetime',
        'deleted_data' => 'array',
        'status' => CommonStatusEnum::class,
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function seo()
    {
        return $this->hasOne(Seo::class, 'slug', 'slug');
    }

    public function scopePublished($query)
    {
        return $query->where('status', CommonStatusEnum::ACTIVE->value)->whereNotNull('published_at')->where('published_at', '<=', now());
    }

    protected static function booted(): void
    {
        static::deleting(function (Page $page) {
            if (! $page->isForceDeleting()) {
                $page->deleted_data = [
                    'slug' => $page->slug,
                    'title' => $page->title,
                ];
                $page->slug = null;
                $page->saveQuietly();
            }
        });

        static::restoring(function (Page $page) {
            if ($page->deleted_data) {
                $page->slug = $page->deleted_data['slug'] ?? $page->slug;
                $page->deleted_data = null;
            }
        });
    }
}
