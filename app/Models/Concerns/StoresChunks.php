<?php

namespace App\Models\Concerns;

use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

trait StoresChunks
{
    abstract protected function chunkRoot(): string;

    abstract protected static function staleAfterHours(): int;

    public function chunkDirectory(): string
    {
        return $this->chunkRoot().'/'.$this->getKey();
    }

    public function chunkPath(int $index): string
    {
        return $this->chunkDirectory().'/'.$index;
    }

    public function expectedChunkSize(int $index): int
    {
        return $index === $this->total_chunks - 1
            ? $this->size - $this->chunk_size * ($this->total_chunks - 1)
            : $this->chunk_size;
    }

    public function receivedChunks(): array
    {
        $disk = Storage::disk('local');

        return collect($disk->files($this->chunkDirectory()))
            ->map(fn ($path) => basename($path))
            ->filter(fn ($name) => ctype_digit($name))
            ->map(fn ($name) => (int) $name)
            ->filter(fn ($index) => $index < $this->total_chunks && $disk->size($this->chunkPath($index)) === $this->expectedChunkSize($index))
            ->sort()
            ->values()
            ->all();
    }

    public function missingChunks(): array
    {
        return array_values(array_diff(range(0, $this->total_chunks - 1), $this->receivedChunks()));
    }

    public function assemble(): string
    {
        $disk = Storage::disk('local');
        $target = $disk->path($this->chunkDirectory().'/assembled');
        $out = fopen($target, 'wb');

        for ($index = 0; $index < $this->total_chunks; $index++) {
            $in = fopen($disk->path($this->chunkPath($index)), 'rb');
            stream_copy_to_stream($in, $out);
            fclose($in);
        }

        fclose($out);
        clearstatcache(true, $target);

        if (filesize($target) !== $this->size) {
            @unlink($target);
            throw ValidationException::withMessages(['file' => 'The uploaded file is incomplete. Please try again.']);
        }

        return $target;
    }

    public function discard(): void
    {
        Storage::disk('local')->deleteDirectory($this->chunkDirectory());
        $this->delete();
    }

    public static function pruneStale(): int
    {
        $stale = static::where('updated_at', '<', now()->subHours(static::staleAfterHours()))->get();
        $stale->each->discard();

        return $stale->count();
    }
}
