<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MediaFile;
use App\Models\MediaFileVersion;
use App\Services\Media\MediaLibrary;
use App\Services\Media\MediaScanner;
use App\Services\Media\MediaWriter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Number;
use Illuminate\Validation\ValidationException;

class MediaLibraryController extends Controller
{
    public const SORTS = [
        'newest' => 'Recently changed',
        'oldest' => 'Oldest first',
        'name' => 'Name (A–Z)',
        'largest' => 'Largest file',
        'smallest' => 'Smallest file',
        'most-used' => 'Most used',
    ];

    public function index(Request $request, MediaScanner $scanner)
    {
        Gate::authorize('admin.media.view');

        if (! MediaScanner::lastScan()) {
            $scanner->scan();
        }

        $query = MediaFile::query();

        if ($request->filled('search') && is_string($request->search)) {
            $term = '%'.str_replace(['%', '_'], ['\%', '\_'], trim($request->search)).'%';
            $query->where(fn ($q) => $q->where('filename', 'like', $term)->orWhere('path', 'like', $term));
        }

        if (in_array($request->location, [MediaFile::STORAGE, MediaFile::PUBLIC], true)) {
            $query->where('location', $request->location);
        }

        if ($request->filled('type') && is_string($request->type)) {
            $query->whereIn('extension', $request->type === 'jpg' ? ['jpg', 'jpeg'] : [$request->type]);
        }

        match ($request->usage) {
            'used' => $query->where('usage_count', '>', 0),
            'unused' => $query->where('usage_count', 0),
            default => null,
        };

        if ($request->size === 'large') {
            $query->large();
        }

        if ($request->filled('folder') && is_string($request->folder) && str_contains($request->folder, ':')) {
            [$location, $folder] = explode(':', $request->folder, 2);
            $query->where('location', $location)->where(fn ($q) => $q->where('folder', $folder)->orWhere('folder', 'like', str_replace(['%', '_'], ['\%', '\_'], $folder).'/%'));
        }

        match ($request->input('sort', 'newest')) {
            'oldest' => $query->orderBy('modified_at')->orderBy('id'),
            'name' => $query->orderBy('filename')->orderBy('id'),
            'largest' => $query->orderByDesc('size')->orderBy('id'),
            'smallest' => $query->orderBy('size')->orderBy('id'),
            'most-used' => $query->orderByDesc('usage_count')->orderBy('filename'),
            default => $query->orderByDesc('modified_at')->orderByDesc('id'),
        };

        $files = $query->paginate(config('media.per_page'))->withQueryString();

        return view('admin.media-library.index', [
            'files' => $files,
            'stats' => $this->stats(),
            'folders' => $this->folders(),
            'lastScan' => MediaScanner::lastScan(),
            'stale' => MediaScanner::isStale(),
        ]);
    }

    public function show(MediaFile $file)
    {
        Gate::authorize('admin.media.view');

        return response()->json($this->details($file));
    }

    public function rescan(MediaScanner $scanner)
    {
        Gate::authorize('admin.media.view');

        return response()->json($scanner->scan());
    }

    public function thumb(MediaFile $file, MediaLibrary $library)
    {
        Gate::authorize('admin.media.view');

        $absolute = $file->absolutePath();
        abort_unless(is_file($absolute), 404);

        $headers = ['Cache-Control' => 'private, max-age=31536000, immutable'];

        if (! $file->isRaster()) {
            return $this->sendImage($absolute, $file->extension, $headers);
        }

        $thumb = rescue(fn () => $library->thumbnail($file), null, report: false);

        return $thumb ? response()->file($thumb, [...$headers, 'Content-Type' => 'image/webp']) : $this->sendImage($absolute, $file->extension, $headers);
    }

    public function versionPreview(MediaFile $file, MediaFileVersion $version)
    {
        Gate::authorize('admin.media.view');
        abort_unless($version->media_file_id === $file->id && Storage::disk('local')->exists($version->backup_path), 404);

        return $this->sendImage(Storage::disk('local')->path($version->backup_path), $file->extension, ['Cache-Control' => 'private, max-age=86400']);
    }

    public function restore(Request $request, MediaFile $file, MediaFileVersion $version, MediaWriter $writer)
    {
        Gate::authorize('admin.media.replace');
        abort_unless($version->media_file_id === $file->id, 404);

        $writer->restore($file, $version, $request->user());

        return response()->json(['file' => $this->details($file->fresh())]);
    }

    public function destroy(MediaFile $file, MediaScanner $scanner)
    {
        Gate::authorize('admin.media.delete');

        if ($file->location !== MediaFile::STORAGE) {
            throw ValidationException::withMessages(['file' => 'Images in the public folder are part of the website code and can only be replaced, not deleted.']);
        }

        $scanner->scan();
        $file->refresh();

        if ($file->usage_count > 0) {
            throw ValidationException::withMessages(['file' => "This image is used in {$file->usage_count} ".str('place')->plural($file->usage_count).'. Remove it from there first.']);
        }

        @unlink($file->absolutePath());
        $file->delete();

        return response()->json(['deleted' => $file->id]);
    }

    private function stats(): array
    {
        $row = MediaFile::query()->selectRaw(
            'COUNT(*) as total, COALESCE(SUM(size), 0) as bytes, SUM(CASE WHEN usage_count = 0 THEN 1 ELSE 0 END) as unused, SUM(CASE WHEN size > ? THEN 1 ELSE 0 END) as large',
            [config('media.large_bytes')],
        )->first();

        return [
            'total' => (int) $row->total,
            'bytes' => (int) $row->bytes,
            'unused' => (int) $row->unused,
            'large' => (int) $row->large,
        ];
    }

    private function folders(): array
    {
        return MediaFile::query()
            ->select('location', 'folder', DB::raw('COUNT(*) as files'))
            ->groupBy('location', 'folder')
            ->orderBy('location')
            ->orderBy('folder')
            ->get()
            ->mapWithKeys(fn ($row) => [
                "{$row->location}:{$row->folder}" => ucfirst($row->location).' › '.($row->folder === '' ? '(root)' : str_replace('/', ' › ', $row->folder))." ({$row->files})",
            ])
            ->all();
    }

    private function details(MediaFile $file): array
    {
        $file->load(['usages', 'versions.user:id,name']);
        $canDelete = Gate::allows('admin.media.delete') && $file->location === MediaFile::STORAGE && $file->usage_count === 0;

        return [
            'id' => $file->id,
            'location' => $file->location,
            'path' => $file->path,
            'display_path' => ($file->location === MediaFile::STORAGE ? 'storage/app/public/' : 'public/').$file->path,
            'filename' => $file->filename,
            'extension' => $file->extension,
            'size' => $file->size,
            'size_label' => Number::fileSize($file->size, 1),
            'dimensions' => $file->width && $file->height ? "{$file->width} × {$file->height}" : null,
            'width' => $file->width,
            'height' => $file->height,
            'modified' => $file->modified_at?->diffForHumans(),
            'modified_full' => local_datetime($file->modified_at),
            'version' => $file->version,
            'is_raster' => $file->isRaster(),
            'is_large' => $file->isLarge(),
            'url' => $file->url,
            'thumb' => $file->thumb_url,
            'accept' => $file->acceptedUploadTypes(),
            'usage_count' => $file->usage_count,
            'usages' => $file->usages->map(fn ($usage) => $usage->only(['source', 'title', 'detail', 'url']))->values(),
            'versions' => $file->versions->map(fn (MediaFileVersion $version) => [
                'id' => $version->id,
                'size_label' => Number::fileSize($version->size, 1),
                'dimensions' => $version->width && $version->height ? "{$version->width} × {$version->height}" : null,
                'created' => $version->created_at->diffForHumans(),
                'created_full' => local_datetime($version->created_at),
                'by' => $version->user?->name,
                'preview' => route('admin.media-library.versions.preview', [$file, $version]),
                'restore_url' => route('admin.media-library.versions.restore', [$file, $version]),
            ])->values(),
            'can_replace' => Gate::allows('admin.media.replace') && $file->acceptedUploadTypes() !== [],
            'can_delete' => $canDelete,
            'delete_url' => $canDelete ? route('admin.media-library.destroy', $file) : null,
        ];
    }

    private function sendImage(string $absolute, string $extension, array $headers)
    {
        $type = match ($extension) {
            'svg' => 'image/svg+xml',
            'ico' => 'image/x-icon',
            'jpg', 'jpeg' => 'image/jpeg',
            default => "image/{$extension}",
        };

        return response()->file($absolute, [
            ...$headers,
            'Content-Type' => $type,
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; img-src data:; style-src 'unsafe-inline'; sandbox",
        ]);
    }
}
