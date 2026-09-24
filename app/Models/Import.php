<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class Import extends Model
{
    use HasUuids;

    public const READY = 'ready';

    public const RUNNING = 'running';

    public const COMPLETED = 'completed';

    protected $fillable = ['type', 'user_id', 'original_name', 'status', 'total', 'counts', 'cursor', 'finished_at'];

    protected $casts = [
        'counts' => 'array',
        'total' => 'integer',
        'cursor' => 'integer',
        'finished_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function rowsPath(): string
    {
        return "imports/{$this->id}.json";
    }

    public function rows(): array
    {
        return json_decode((string) Storage::disk('local')->get($this->rowsPath()), true) ?: [];
    }

    public function saveRows(array $rows): void
    {
        Storage::disk('local')->put($this->rowsPath(), json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    public function tally(string $key): int
    {
        return (int) ($this->counts[$key] ?? 0);
    }

    public function percent(): int
    {
        $todo = $this->tally('new') + $this->tally('created') + $this->tally('failed');

        if ($this->status === self::COMPLETED || $todo === 0) {
            return 100;
        }

        return (int) floor(($this->tally('created') + $this->tally('failed')) / $todo * 100);
    }

    protected static function booted(): void
    {
        static::deleting(fn (Import $import) => Storage::disk('local')->delete($import->rowsPath()));
    }
}
