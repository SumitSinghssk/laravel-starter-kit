<?php

namespace App\Http\Controllers\Admin\Setting;

use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class LogController extends Controller
{
    use AuthorizesRequests;

    protected string $logPath;

    public function __construct()
    {
        $this->logPath = storage_path('logs');
    }

    public function index()
    {
        $this->authorize('admin.log-settings.view');

        return response()->json([
            'logFiles' => $this->getLogFiles(),
        ]);
    }

    public function show(Request $request)
    {
        $this->authorize('admin.log-settings.view');

        $filename = $request->query('file');

        if (! $filename || ! $this->isValidLogFile($filename)) {
            return response()->json(['error' => 'Invalid log file.'], 422);
        }

        $filePath = $this->logPath.DIRECTORY_SEPARATOR.$filename;

        if (! File::exists($filePath)) {
            return response()->json(['error' => 'Log file not found.'], 404);
        }

        return response()->json([
            'filename' => $filename,
            'content' => $this->readLastLines($filePath, 500),
            'size' => $this->formatSize(File::size($filePath)),
        ]);
    }

    public function destroy(Request $request)
    {
        $this->authorize('admin.log-settings.delete');

        $filename = $request->input('file');

        if (! $filename || ! $this->isValidLogFile($filename)) {
            return back()->withErrors(['file' => 'Invalid log file.']);
        }

        $filePath = $this->logPath.DIRECTORY_SEPARATOR.$filename;

        if (! File::exists($filePath)) {
            return back()->withErrors(['file' => 'Log file not found.']);
        }

        File::delete($filePath);

        return back()->with('success', "Log file '{$filename}' deleted successfully.");
    }

    public function destroyAll()
    {
        $this->authorize('admin.log-settings.delete');

        $files = File::glob($this->logPath.DIRECTORY_SEPARATOR.'*.log');

        foreach ($files as $file) {
            File::delete($file);
        }

        return back()->with('success', 'All log files deleted successfully.');
    }

    public function getLogFiles(): array
    {
        if (! File::isDirectory($this->logPath)) {
            return [];
        }

        $files = File::glob($this->logPath.DIRECTORY_SEPARATOR.'*.log');

        return collect($files)
            ->map(function (string $path) {
                $size = File::size($path);
                $modified = File::lastModified($path);

                return [
                    'filename' => basename($path),
                    'size' => $this->formatSize($size),
                    'size_raw' => $size,
                    'modified' => date('Y-m-d H:i:s', $modified),
                    'modified_ts' => $modified,
                ];
            })
            ->sortByDesc('modified_ts')
            ->values()
            ->toArray();
    }

    private function isValidLogFile(string $filename): bool
    {
        return Str::endsWith($filename, '.log')
            && ! Str::contains($filename, ['/', '\\', '..']);
    }

    private function formatSize(int $bytes): string
    {
        if ($bytes >= 1_048_576) {
            return round($bytes / 1_048_576, 2).' MB';
        }
        if ($bytes >= 1_024) {
            return round($bytes / 1_024, 2).' KB';
        }

        return $bytes.' B';
    }

    private function readLastLines(string $filePath, int $lines = 500): string
    {
        $file = new \SplFileObject($filePath, 'r');
        $file->seek(PHP_INT_MAX);
        $totalLines = $file->key();

        $startLine = max(0, $totalLines - $lines);
        $output = [];

        $file->seek($startLine);
        while (! $file->eof()) {
            $output[] = $file->fgets();
        }

        return implode('', $output);
    }
}
