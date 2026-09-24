<?php

namespace App\Models;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Number;

class Backup extends Model
{
    public const RUNNING = 'running';

    public const COMPLETED = 'completed';

    public const FAILED = 'failed';

    protected $fillable = [
        'name',
        'trigger',
        'includes_database',
        'includes_files',
        'status',
        'stage',
        'progress',
        'cursor',
        'parts',
        'size',
        'error',
        'scheduled_for',
        'started_at',
        'finished_at',
        'last_step_at',
        'user_id',
    ];

    protected $casts = [
        'includes_database' => 'boolean',
        'includes_files' => 'boolean',
        'progress' => 'array',
        'cursor' => 'array',
        'parts' => 'array',
        'size' => 'integer',
        'scheduled_for' => 'datetime',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'last_step_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function disk(): Filesystem
    {
        return Storage::disk(config('backup.disk'));
    }

    public function directory(): string
    {
        return config('backup.directory').'/'.$this->name;
    }

    public function path(string $file): string
    {
        return $this->directory().'/'.$file;
    }

    public function isRunning(): bool
    {
        return $this->status === self::RUNNING;
    }

    public function isStalled(): bool
    {
        return $this->isRunning() && (! $this->last_step_at || $this->last_step_at->lt(now()->subMinutes(config('backup.stall_minutes'))));
    }

    public function percent(): int
    {
        if ($this->status === self::COMPLETED) {
            return 100;
        }

        $p = $this->progress ?? [];
        $parts = [];

        if ($this->includes_database) {
            $parts[] = ($p['tables_total'] ?? 0) ? ($p['tables_done'] ?? 0) / $p['tables_total'] : ($this->stage === 'database' ? 0 : 1);
        }

        if ($this->includes_files) {
            $parts[] = ($p['files_total'] ?? 0) ? ($p['files_done'] ?? 0) / $p['files_total'] : ($this->stage === 'done' ? 1 : 0);
        }

        return $parts ? (int) floor(array_sum($parts) / count($parts) * 100) : 0;
    }

    public function statusLine(): string
    {
        $p = $this->progress ?? [];

        return match (true) {
            $this->status === self::COMPLETED => 'Completed',
            $this->status === self::FAILED => 'Failed',
            $this->stage === 'database' => isset($p['current_table'])
                ? "Database: table {$p['current_table']} (".(($p['tables_done'] ?? 0) + 1)." of {$p['tables_total']})"
                : 'Database: starting…',
            $this->stage === 'files' => ($p['files_total'] ?? null) !== null
                ? 'Files: '.number_format($p['files_done'] ?? 0).' of '.number_format($p['files_total'])
                : 'Files: listing…',
            default => 'Finishing…',
        };
    }

    public function progressPayload(): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'percent' => $this->percent(),
            'status_line' => $this->statusLine(),
            'error' => $this->error,
            'size' => Number::fileSize($this->size ?? 0, 1),
            'step_url' => route('admin.backups.step', $this),
        ];
    }

    protected static function booted(): void
    {
        static::deleting(fn (Backup $backup) => static::disk()->deleteDirectory($backup->directory()));
    }
}
