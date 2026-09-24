<?php

namespace App\Models;

use App\Enums\CommonStatusEnum;
use App\Traits\Trackable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Gallery extends Model
{
    use Trackable;

    protected $fillable = [
        'title',
        'slug',
        'description',
        'event_date',
        'is_featured',
        'sort_order',
        'status',
    ];

    protected $casts = [
        'status' => CommonStatusEnum::class,
        'event_date' => 'date',
        'is_featured' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(GalleryItem::class)->orderBy('sort_order')->orderBy('id');
    }

    public function coverItem(): BelongsTo
    {
        return $this->belongsTo(GalleryItem::class, 'cover_item_id');
    }

    public function cover(): ?GalleryItem
    {
        return $this->coverItem ?? $this->firstItem;
    }

    public function firstItem(): HasOne
    {
        return $this->hasOne(GalleryItem::class)->ofMany(['sort_order' => 'min', 'id' => 'min']);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', CommonStatusEnum::ACTIVE->value);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderByDesc('is_featured')->orderBy('sort_order')->orderByDesc('event_date')->latest('id');
    }

    protected static function booted(): void
    {
        static::deleting(function (Gallery $gallery) {
            $gallery->items()->each(fn (GalleryItem $item) => $item->delete());
            GalleryUpload::where('gallery_id', $gallery->id)->each(fn (GalleryUpload $upload) => $upload->discard());
        });
    }
}
