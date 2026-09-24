<?php

namespace App\Models;

use App\Models\Concerns\StoresChunks;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GalleryUpload extends Model
{
    use HasUuids, StoresChunks;

    protected $fillable = [
        'gallery_id',
        'user_id',
        'fingerprint',
        'original_name',
        'kind',
        'size',
        'chunk_size',
        'total_chunks',
    ];

    protected $casts = [
        'size' => 'integer',
        'chunk_size' => 'integer',
        'total_chunks' => 'integer',
    ];

    public function gallery(): BelongsTo
    {
        return $this->belongsTo(Gallery::class);
    }

    protected function chunkRoot(): string
    {
        return 'gallery-chunks';
    }

    protected static function staleAfterHours(): int
    {
        return (int) config('gallery.stale_upload_hours');
    }
}
