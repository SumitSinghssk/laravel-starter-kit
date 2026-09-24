<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class MediaFileVersion extends Model
{
    protected $fillable = [
        'media_file_id',
        'backup_path',
        'size',
        'width',
        'height',
        'user_id',
    ];

    protected $casts = [
        'media_file_id' => 'integer',
        'size' => 'integer',
        'width' => 'integer',
        'height' => 'integer',
    ];

    public function mediaFile(): BelongsTo
    {
        return $this->belongsTo(MediaFile::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected static function booted(): void
    {
        static::deleting(fn (MediaFileVersion $version) => Storage::disk('local')->delete($version->backup_path));
    }
}
