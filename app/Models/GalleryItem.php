<?php

namespace App\Models;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class GalleryItem extends Model
{
    public const IMAGE = 'image';

    public const VIDEO = 'video';

    public const YOUTUBE = 'youtube';

    protected $fillable = [
        'gallery_id',
        'type',
        'path',
        'thumbnail_path',
        'youtube_id',
        'title',
        'caption',
        'width',
        'height',
        'duration',
        'size',
        'mime',
        'sort_order',
    ];

    protected $casts = [
        'width' => 'integer',
        'height' => 'integer',
        'duration' => 'integer',
        'size' => 'integer',
        'sort_order' => 'integer',
    ];

    public function gallery(): BelongsTo
    {
        return $this->belongsTo(Gallery::class);
    }

    public static function disk(): Filesystem
    {
        return Storage::disk(config('gallery.disk'));
    }

    public function getUrlAttribute(): ?string
    {
        return media_url($this->path);
    }

    public function getThumbnailUrlAttribute(): ?string
    {
        if ($this->thumbnail_path) {
            return media_url($this->thumbnail_path);
        }

        return $this->youtube_id ? "https://i.ytimg.com/vi/{$this->youtube_id}/hqdefault.jpg" : null;
    }

    public function getEmbedUrlAttribute(): ?string
    {
        return $this->youtube_id ? "https://www.youtube-nocookie.com/embed/{$this->youtube_id}" : null;
    }

    public function getWatchUrlAttribute(): ?string
    {
        return $this->youtube_id ? "https://www.youtube.com/watch?v={$this->youtube_id}" : null;
    }

    public function toAdminArray(?int $coverId = null): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'title' => $this->title,
            'caption' => $this->caption,
            'thumb' => $this->thumbnail_url,
            'url' => match ($this->type) {
                self::IMAGE => $this->url,
                self::VIDEO => route('admin.gallery-media.stream', $this),
                self::YOUTUBE => $this->embed_url,
            },
            'external' => $this->watch_url,
            'width' => $this->width,
            'height' => $this->height,
            'duration' => $this->duration,
            'size' => $this->size,
            'mime' => $this->mime,
            'is_cover' => $coverId === $this->id,
            'custom_thumb' => $this->type !== self::IMAGE && (bool) $this->thumbnail_path,
            'update_url' => route('admin.gallery-media.update', $this),
            'delete_url' => route('admin.gallery-media.destroy', $this),
        ];
    }

    protected static function booted(): void
    {
        static::deleting(function (GalleryItem $item) {
            static::disk()->delete(array_filter([$item->path, $item->thumbnail_path]));

            Gallery::whereKey($item->gallery_id)->where('cover_item_id', $item->id)->update(['cover_item_id' => null]);
        });
    }
}
