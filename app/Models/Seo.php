<?php

namespace App\Models;

use App\Traits\Trackable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Seo extends Model
{
    use Trackable;

    protected $fillable = [
        'page',
        'slug',
        'meta_title',
        'meta_description',
        'og_image',
        'schema',
        'faqs',
        'header_scripts',
        'footer_scripts',
        'custom_css',
        'index',
    ];

    protected $casts = [
        'faqs' => 'array',
        'index' => 'boolean',
    ];

    protected static function booted()
    {
        static::saved(function () {
            Cache::forget('seo_all');
        });

        static::deleted(function () {
            Cache::forget('seo_all');
        });
    }
}
