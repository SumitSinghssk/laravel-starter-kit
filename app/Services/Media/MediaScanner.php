<?php

namespace App\Services\Media;

use App\Models\MediaFile;
use App\Models\MediaUsage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class MediaScanner
{
    public const LAST_SCAN_KEY = 'media-library:scanned-at';

    public function __construct(private MediaLibrary $library, private MediaUsageFinder $usageFinder) {}

    public static function lastScan(): ?Carbon
    {
        $timestamp = Cache::get(self::LAST_SCAN_KEY);

        return $timestamp ? Carbon::createFromTimestamp($timestamp) : null;
    }

    public static function isStale(): bool
    {
        $last = self::lastScan();

        return ! $last || $last->lt(now()->subMinutes(config('media.rescan_after_minutes')));
    }

    public function scan(): array
    {
        $lock = Cache::lock('media-library:scan', 300);

        if (! $lock->get()) {
            return ['added' => 0, 'updated' => 0, 'removed' => 0, 'total' => MediaFile::count(), 'changed' => false, 'busy' => true];
        }

        try {
            $stats = $this->syncFiles();
            $usageChanged = $this->syncUsages();

            Cache::forever(self::LAST_SCAN_KEY, now()->timestamp);

            return [...$stats, 'total' => MediaFile::count(), 'changed' => $stats['added'] + $stats['updated'] + $stats['removed'] > 0 || $usageChanged];
        } finally {
            $lock->release();
        }
    }

    private function syncFiles(): array
    {
        $existing = MediaFile::all(['id', 'location', 'path', 'size', 'modified_at'])->keyBy(fn ($file) => "{$file->location}:{$file->path}");
        $seen = [];
        $added = $updated = 0;

        foreach ($this->library->roots() as $location => $root) {
            foreach ($this->filesIn($location, $root) as $path) {
                $key = "{$location}:{$path}";
                $seen[$key] = true;
                $record = $existing->get($key);
                $absolute = $root.'/'.$path;

                if ($record && $record->size === filesize($absolute) && $record->modified_at?->timestamp === filemtime($absolute)) {
                    continue;
                }

                $attributes = $this->library->describe($location, $path);

                if ($record) {
                    $record->update($attributes);
                    $updated++;
                } else {
                    MediaFile::create($attributes);
                    $added++;
                }
            }
        }

        $gone = $existing->reject(fn ($file, $key) => isset($seen[$key]));
        $gone->each(fn (MediaFile $file) => $file->delete());

        return ['added' => $added, 'updated' => $updated, 'removed' => $gone->count()];
    }

    private function filesIn(string $location, string $root): \Generator
    {
        if (! is_dir($root)) {
            return;
        }

        $extensions = config('media.extensions');
        $directory = new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS);

        $filter = new \RecursiveCallbackFilterIterator($directory, function (\SplFileInfo $file) use ($location, $root) {
            $relative = ltrim(str_replace('\\', '/', substr($file->getPathname(), strlen($root))), '/');

            return ! $file->isLink() && ! $this->library->isExcluded($location, $relative);
        });

        foreach (new RecursiveIteratorIterator($filter) as $file) {
            if ($file->isFile() && in_array(strtolower($file->getExtension()), $extensions, true)) {
                yield ltrim(str_replace('\\', '/', substr($file->getPathname(), strlen($root))), '/');
            }
        }
    }

    private function syncUsages(): bool
    {
        $usages = $this->usageFinder->find();
        $files = MediaFile::all(['id', 'location', 'path', 'usage_count']);
        $now = now();
        $rows = [];
        $changed = false;

        foreach ($files as $file) {
            $found = $usages["{$file->location}:{$file->path}"] ?? [];

            foreach ($found as $usage) {
                $rows[] = [...$usage, 'media_file_id' => $file->id, 'created_at' => $now, 'updated_at' => $now];
            }

            if ($file->usage_count !== count($found)) {
                $file->forceFill(['usage_count' => count($found)])->saveQuietly();
                $changed = true;
            }
        }

        DB::transaction(function () use ($rows) {
            MediaUsage::query()->delete();

            foreach (array_chunk($rows, 500) as $chunk) {
                MediaUsage::insert($chunk);
            }
        });

        return $changed;
    }
}
