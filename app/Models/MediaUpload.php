<?php

namespace App\Models;

use App\Models\Concerns\StoresChunks;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MediaUpload extends Model
{
    use HasUuids, StoresChunks;

    protected $fillable = [
        'user_id',
        'media_file_id',
        'folder',
        'fingerprint',
        'original_name',
        'size',
        'chunk_size',
        'total_chunks',
    ];

    protected $casts = [
        'size' => 'integer',
        'chunk_size' => 'integer',
        'total_chunks' => 'integer',
    ];

    public function mediaFile(): BelongsTo
    {
        return $this->belongsTo(MediaFile::class);
    }

    protected function chunkRoot(): string
    {
        return 'media-chunks';
    }

    protected static function staleAfterHours(): int
    {
        return 24;
    }
}
