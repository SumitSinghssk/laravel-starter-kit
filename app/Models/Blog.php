<?php

namespace App\Models;

use App\Enums\CommonStatusEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Blog extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'title',
        'slug',
        'excerpt',
        'content',
        'featured_image',
        'status',
        'published_at',
    ];

    protected $casts = [
        'status' => CommonStatusEnum::class,
        'published_at' => 'datetime',
        'deleted_data' => 'array',
    ];

    public function author()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function categories()
    {
        return $this->belongsToMany(BlogCategory::class, 'blog_category_blog');
    }

    public function seo()
    {
        return $this->hasOne(Seo::class, 'slug', 'slug');
    }

    protected static function booted(): void
    {
        static::deleting(function (Blog $blog) {
            if (! $blog->isForceDeleting()) {
                $blog->deleted_data = [
                    'slug' => $blog->slug,
                    'title' => $blog->title,
                ];

                $blog->slug = null;

                $blog->saveQuietly();
            }
        });

        static::restoring(function (Blog $blog) {
            if ($blog->deleted_data) {
                $blog->slug = $blog->deleted_data['slug'] ?? $blog->slug;
                $blog->deleted_data = null;
            }
        });
    }
}
