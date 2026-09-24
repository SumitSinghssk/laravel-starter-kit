<?php

namespace App\Services\Backup;

use App\Models\Backup;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Throwable;
use ZipArchive;

class BackupRunner
{
    private const DATABASE_FILE = 'database.sql.gz';

    private const FILE_LIST = 'files.txt';

    private const STORE_ONLY = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'avif', 'mp4', 'webm', 'mov', 'zip', 'gz', 'pdf', 'woff', 'woff2'];

    public function __construct(private DatabaseDumper $dumper) {}

    public function start(string $trigger, bool $database, bool $files, ?User $user = null, ?Carbon $scheduledFor = null): Backup
    {
        if (! $database && ! $files) {
            throw ValidationException::withMessages(['backup' => 'Choose the database, the uploaded files, or both.']);
        }

        $running = Backup::where('status', Backup::RUNNING)->latest('id')->first();

        if ($running && ! $running->isStalled()) {
            throw ValidationException::withMessages(['backup' => 'A backup is already running. Wait for it to finish.']);
        }

        $running?->forceFill(['status' => Backup::FAILED, 'error' => 'Interrupted and replaced by a newer backup.'])->save();
        $running && Backup::disk()->deleteDirectory($running->directory());

        $name = 'backup-'.now()->format('Y-m-d-His');
        for ($i = 2; Backup::where('name', $name)->exists(); $i++) {
            $name = 'backup-'.now()->format('Y-m-d-His')."-{$i}";
        }

        $backup = Backup::create([
            'name' => $name,
            'trigger' => $trigger,
            'includes_database' => $database,
            'includes_files' => $files,
            'status' => Backup::RUNNING,
            'stage' => $database ? 'database' : 'files',
            'progress' => [],
            'cursor' => [],
            'parts' => [],
            'scheduled_for' => $scheduledFor,
            'started_at' => now(),
            'last_step_at' => now(),
            'user_id' => $user?->id,
        ]);

        Backup::disk()->makeDirectory($backup->directory());

        return $backup;
    }

    public function step(Backup $backup, int $seconds): Backup
    {
        $lock = Cache::lock('backup-step:'.$backup->id, $seconds + 120);

        if (! $lock->get()) {
            return $backup->refresh();
        }

        try {
            $backup->refresh();

            if (! $backup->isRunning()) {
                return $backup;
            }

            @set_time_limit($seconds + 120);
            $deadline = microtime(true) + $seconds;

            do {
                match ($backup->stage) {
                    'database' => $this->databaseBatch($backup),
                    'files' => $this->filesPart($backup, $deadline),
                    default => $this->finish($backup),
                };

                $backup->forceFill(['last_step_at' => now()])->save();
            } while ($backup->isRunning() && microtime(true) < $deadline);
        } catch (Throwable $e) {
            report($e);
            $this->fail($backup, $e->getMessage());
        } finally {
            $lock->release();
        }

        return $backup;
    }

    public function runToCompletion(Backup $backup, ?callable $onProgress = null): Backup
    {
        while ($backup->refresh()->isRunning()) {
            $this->step($backup, config('backup.step_seconds.cli'));
            $onProgress && $onProgress($backup);
        }

        return $backup;
    }

    public function prune(int $keep): int
    {
        $old = Backup::where('status', Backup::COMPLETED)->latest('id')->skip(max(1, $keep))->take(PHP_INT_MAX)->get();
        $failed = Backup::where('status', Backup::FAILED)->where('created_at', '<', now()->subDay())->get();

        $old->merge($failed)->each->delete();

        return $old->count() + $failed->count();
    }

    private function databaseBatch(Backup $backup): void
    {
        $cursor = $backup->cursor ?? [];
        $progress = $backup->progress ?? [];
        $file = Backup::disk()->path($backup->path(self::DATABASE_FILE));

        if (! isset($cursor['tables'])) {
            $cursor = ['tables' => $this->dumper->tables(), 'table' => 0, 'rows' => [], 'structure' => false];
            $progress = [...$progress, 'tables_total' => count($cursor['tables']), 'tables_done' => 0, 'rows_done' => 0];
            $this->appendGzip($file, $this->dumper->header(), 0);
            $cursor['bytes'] = filesize($file);
        }

        $table = $cursor['tables'][$cursor['table']] ?? null;

        if ($table === null) {
            $this->appendGzip($file, $this->dumper->footer(), $cursor['bytes']);
            $this->addPart($backup, self::DATABASE_FILE, 'Database (.sql.gz)');
            $this->save($backup, $backup->includes_files ? 'files' : 'done', [], [...$progress, 'current_table' => null]);

            return;
        }

        $progress['current_table'] = $table;

        if (! $cursor['structure']) {
            $cursor['bytes'] = $this->appendGzip($file, $this->dumper->structure($table), $cursor['bytes']);
            $cursor['structure'] = true;
            $cursor['key'] = $this->dumper->primaryKey($table);
            $this->save($backup, 'database', $cursor, $progress);

            return;
        }

        $limit = config('backup.rows_per_query');
        $batch = in_array($table, config('backup.structure_only'), true)
            ? ['sql' => '', 'rows' => 0, 'cursor' => $cursor['rows']]
            : $this->dumper->rows($table, $cursor['key'], $cursor['rows'], $limit);

        if ($batch['rows']) {
            $cursor['bytes'] = $this->appendGzip($file, $batch['sql'], $cursor['bytes']);
            $cursor['rows'] = $batch['cursor'];
            $progress['rows_done'] = ($progress['rows_done'] ?? 0) + $batch['rows'];
        }

        if ($batch['rows'] < $limit) {
            $cursor = [...$cursor, 'table' => $cursor['table'] + 1, 'rows' => [], 'structure' => false];
            $progress['tables_done'] = ($progress['tables_done'] ?? 0) + 1;
        }

        $this->save($backup, 'database', $cursor, $progress);
    }

    private function appendGzip(string $file, string $sql, int $expectedSize): int
    {
        clearstatcache(true, $file);

        if (is_file($file) && filesize($file) > $expectedSize) {
            $handle = fopen($file, 'r+');
            ftruncate($handle, $expectedSize);
            fclose($handle);
        }

        $gz = gzopen($file, $expectedSize === 0 ? 'wb6' : 'ab6');
        gzwrite($gz, $sql);
        gzclose($gz);
        clearstatcache(true, $file);

        return filesize($file);
    }

    private function filesPart(Backup $backup, float $deadline): void
    {
        $cursor = $backup->cursor ?? [];
        $progress = $backup->progress ?? [];
        $root = rtrim(str_replace('\\', '/', config('backup.files_root')), '/');
        $listPath = Backup::disk()->path($backup->path(self::FILE_LIST));

        if (! isset($cursor['listed'])) {
            [$count, $bytes] = $this->writeFileList($root, $listPath);
            $this->save($backup, 'files', ['listed' => true, 'index' => 0, 'part' => 0], [...$progress, 'files_total' => $count, 'files_done' => 0, 'bytes_total' => $bytes]);

            return;
        }

        $files = is_file($listPath) ? file($listPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : [];

        if ($cursor['index'] >= count($files)) {
            @unlink($listPath);
            $this->save($backup, 'done', $cursor, $progress);

            return;
        }

        $secondsLeft = max(1, $deadline - microtime(true));
        $maxBytes = (int) min(config('backup.part_max_bytes'), max(5 * 1024 * 1024, $secondsLeft * 10 * 1024 * 1024));
        $maxFiles = config('backup.part_max_files');

        $part = $cursor['part'] + 1;
        $name = sprintf('files-part-%03d.zip', $part);
        $zipPath = Backup::disk()->path($backup->path($name));

        $zip = new ZipArchive;
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException("Could not create {$name}.");
        }

        $index = $cursor['index'];
        $added = 0;
        $bytes = 0;

        while ($index < count($files) && $added < $maxFiles && ($added === 0 || $bytes < $maxBytes)) {
            $relative = $files[$index++];
            $absolute = $root.'/'.$relative;

            if (! is_file($absolute)) {
                continue;
            }

            $entry = 'storage/app/public/'.$relative;
            $zip->addFile($absolute, $entry);

            if (in_array(strtolower(pathinfo($relative, PATHINFO_EXTENSION)), self::STORE_ONLY, true)) {
                $zip->setCompressionName($entry, ZipArchive::CM_STORE);
            }

            $added++;
            $bytes += filesize($absolute);
        }

        if ($added > 0 && ! $zip->close()) {
            throw new \RuntimeException("Could not write {$name}.");
        }

        if ($added > 0) {
            $this->addPart($backup, $name, $cursor['part'] === 0 && $index >= count($files) ? 'Uploaded files (.zip)' : "Uploaded files, part {$part} (.zip)");
        }

        $this->save($backup, 'files', [...$cursor, 'index' => $index, 'part' => $added > 0 ? $part : $cursor['part']], [...$progress, 'files_done' => $index]);
    }

    private function writeFileList(string $root, string $listPath): array
    {
        $paths = [];
        $bytes = 0;

        if (is_dir($root)) {
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS));

            foreach ($iterator as $file) {
                if ($file->isFile() && ! $file->isLink() && $file->getFilename() !== '.gitignore') {
                    $paths[] = ltrim(str_replace('\\', '/', substr($file->getPathname(), strlen($root))), '/');
                    $bytes += $file->getSize();
                }
            }
        }

        sort($paths);
        file_put_contents($listPath, implode("\n", $paths));

        return [count($paths), $bytes];
    }

    private function addPart(Backup $backup, string $file, string $label): void
    {
        $parts = array_values(array_filter($backup->parts ?? [], fn ($part) => $part['file'] !== $file));
        $parts[] = ['file' => $file, 'label' => $label, 'size' => Backup::disk()->size($backup->path($file))];

        $backup->parts = $parts;
    }

    private function save(Backup $backup, string $stage, array $cursor, array $progress): void
    {
        $backup->forceFill(['stage' => $stage, 'cursor' => $cursor, 'progress' => $progress])->save();
    }

    private function finish(Backup $backup): void
    {
        $backup->forceFill([
            'status' => Backup::COMPLETED,
            'stage' => 'done',
            'cursor' => null,
            'size' => collect($backup->parts)->sum('size'),
            'finished_at' => now(),
        ])->save();

        $this->prune((int) app(BackupSchedule::class)->settings()['keep']);
    }

    private function fail(Backup $backup, string $message): void
    {
        Backup::disk()->deleteDirectory($backup->directory());

        $backup->forceFill([
            'status' => Backup::FAILED,
            'error' => mb_substr($message, 0, 1000),
            'parts' => [],
            'finished_at' => now(),
        ])->save();
    }
}
