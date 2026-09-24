<?php

namespace App\Models;

use App\Services\Media\MediaLibrary;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class MediaFile extends Model
{
    public const STORAGE = 'storage';

    public const PUBLIC = 'public';

    protected $fillable = [
        'location',
        'path',
        'folder',
        'filename',
        'extension',
        'size',
        'width',
        'height',
        'modified_at',
        'version',
        'usage_count',
    ];

    protected $casts = [
        'size' => 'integer',
        'width' => 'integer',
        'height' => 'integer',
        'modified_at' => 'datetime',
        'version' => 'integer',
        'usage_count' => 'integer',
    ];

    public function versions(): HasMany
    {
        return $this->hasMany(MediaFileVersion::class)->latest('id');
    }

    public function usages(): HasMany
    {
        return $this->hasMany(MediaUsage::class)->orderBy('source')->orderBy('title');
    }

    public function scopeLarge(Builder $query): Builder
    {
        return $query->where('size', '>', config('media.large_bytes'));
    }

    public function absolutePath(): string
    {
        return app(MediaLibrary::class)->absolutePath($this->location, $this->path);
    }

    public function getUrlAttribute(): string
    {
        return app(MediaLibrary::class)->url($this->location, $this->path).'?v='.($this->modified_at?->timestamp ?? $this->version);
    }

    public function getThumbUrlAttribute(): string
    {
        return route('admin.media-library.thumb', ['file' => $this->id, 'v' => $this->version]);
    }

    public function isRaster(): bool
    {
        return in_array($this->extension, config('media.raster'), true);
    }

    public function isLarge(): bool
    {
        return $this->size > config('media.large_bytes');
    }

    public function acceptedUploadTypes(): array
    {
        return match (true) {
            $this->isRaster() => ['image/jpeg', 'image/png', 'image/webp', 'image/gif'],
            $this->extension === 'svg' => ['image/svg+xml'],
            $this->extension === 'ico' => ['image/x-icon', 'image/vnd.microsoft.icon'],
            default => [],
        };
    }

    protected static function booted(): void
    {
        static::deleting(function (MediaFile $file) {
            Storage::disk('local')->deleteDirectory("media-versions/{$file->id}");
            app(MediaLibrary::class)->forgetThumbnails($file);
        });
    }
}
