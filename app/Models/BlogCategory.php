<?php

namespace App\Models;

use App\Enums\CommonStatusEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class BlogCategory extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'parent_id',
        'image',
        'status',
        'description',
    ];

    protected $casts = [
        'deleted_data' => 'array',
        'status' => CommonStatusEnum::class,
    ];

    public function parent()
    {
        return $this->belongsTo(BlogCategory::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(BlogCategory::class, 'parent_id');
    }

    public function seo()
    {
        return $this->hasOne(Seo::class, 'slug', 'slug');
    }

    public function blogs()
    {
        return $this->belongsToMany(Blog::class, 'blog_category_blog');
    }

    protected static function booted(): void
    {
        static::deleting(function (BlogCategory $category) {
            if (! $category->isForceDeleting()) {
                $category->deleted_data = [
                    'slug' => $category->slug,
                    'name' => $category->name,
                ];

                $category->slug = null;

                $category->saveQuietly();
            }
        });

        static::restoring(function (BlogCategory $category) {
            if ($category->deleted_data) {
                $category->slug = $category->deleted_data['slug'] ?? $category->slug;
                $category->deleted_data = null;
            }
        });
    }
}
