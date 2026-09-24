<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MediaFile;
use App\Models\MediaUpload;
use App\Services\Media\MediaLibrary;
use App\Services\Media\MediaWriter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Number;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class MediaUploadController extends Controller
{
    private const TYPES = ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'image/svg+xml', 'image/x-icon', 'image/vnd.microsoft.icon'];

    private const EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'svg', 'ico'];

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'size' => ['required', 'integer', 'min:1'],
            'mime' => ['nullable', 'string', 'max:100'],
            'fingerprint' => ['required', 'string', 'max:255'],
            'media_file_id' => ['nullable', 'integer', Rule::exists('media_files', 'id')],
            'folder' => ['nullable', 'string', 'max:200'],
        ]);

        $file = isset($validated['media_file_id']) ? MediaFile::find($validated['media_file_id']) : null;
        Gate::authorize($file ? 'admin.media.replace' : 'admin.media.upload');

        MediaUpload::pruneStale();

        $extension = strtolower(pathinfo($validated['name'], PATHINFO_EXTENSION));
        $mime = $validated['mime'] ?? '';

        if (! in_array($mime, self::TYPES, true) && ! in_array($extension, self::EXTENSIONS, true)) {
            throw ValidationException::withMessages(['file' => 'Only JPG, PNG, WebP, GIF, SVG and ICO images can be uploaded.']);
        }

        if ($validated['size'] > config('media.max_size')) {
            throw ValidationException::withMessages(['file' => 'Images can be at most '.Number::fileSize(config('media.max_size')).'. This file is '.Number::fileSize($validated['size']).'.']);
        }

        $folder = $file ? null : $this->folder($validated['folder'] ?? null);
        $fingerprint = hash('sha256', $validated['fingerprint'].'|'.($file?->id ?? $folder));

        $upload = MediaUpload::where('user_id', $request->user()->id)
            ->where('fingerprint', $fingerprint)
            ->where('size', $validated['size'])
            ->first();

        $upload ??= MediaUpload::create([
            'user_id' => $request->user()->id,
            'media_file_id' => $file?->id,
            'folder' => $folder,
            'fingerprint' => $fingerprint,
            'original_name' => $validated['name'],
            'size' => $validated['size'],
            'chunk_size' => config('media.chunk_size'),
            'total_chunks' => (int) ceil($validated['size'] / config('media.chunk_size')),
        ]);

        return response()->json([
            'id' => $upload->id,
            'chunk_size' => $upload->chunk_size,
            'total_chunks' => $upload->total_chunks,
            'received' => $upload->receivedChunks(),
            'chunk_url' => route('admin.media-library.uploads.chunk', [$upload, '__INDEX__']),
            'complete_url' => route('admin.media-library.uploads.complete', $upload),
            'cancel_url' => route('admin.media-library.uploads.destroy', $upload),
        ]);
    }

    public function chunk(Request $request, MediaUpload $upload, int $index)
    {
        $this->authorizeUpload($request, $upload);

        abort_unless($index >= 0 && $index < $upload->total_chunks, 422, 'Unknown chunk.');

        $request->validate(['chunk' => ['required', 'file']]);

        if ($request->file('chunk')->getSize() !== $upload->expectedChunkSize($index)) {
            throw ValidationException::withMessages(['chunk' => 'This chunk arrived incomplete. It will be sent again.']);
        }

        Storage::disk('local')->putFileAs($upload->chunkDirectory(), $request->file('chunk'), (string) $index);
        $upload->touch();

        return response()->json(['index' => $index]);
    }

    public function complete(Request $request, MediaUpload $upload, MediaWriter $writer)
    {
        $this->authorizeUpload($request, $upload);

        $validated = $request->validate(['fit' => ['nullable', Rule::in([MediaWriter::FIT_KEEP, MediaWriter::FIT_COVER])]]);

        $lock = Cache::lock('media-upload:'.$upload->id, 120);
        abort_unless($lock->get(), 409, 'This upload is already being finished.');

        try {
            if ($missing = $upload->missingChunks()) {
                return response()->json(['message' => 'Some chunks are missing.', 'missing' => $missing], 422);
            }

            $assembled = $upload->assemble();

            try {
                $file = $upload->media_file_id
                    ? $writer->replace($upload->mediaFile, $assembled, $validated['fit'] ?? MediaWriter::FIT_KEEP, $request->user())
                    : $writer->storeNew($assembled, $upload->original_name, (string) $upload->folder);
            } catch (ValidationException $e) {
                $upload->discard();
                throw $e;
            } finally {
                @unlink($assembled);
            }

            $upload->discard();

            return response()->json([
                'file' => [
                    'id' => $file->id,
                    'filename' => $file->filename,
                    'thumb' => $file->thumb_url,
                    'url' => $file->url,
                    'size_label' => Number::fileSize($file->size, 1),
                    'dimensions' => $file->width && $file->height ? "{$file->width} × {$file->height}" : null,
                    'version' => $file->version,
                ],
            ], $upload->media_file_id ? 200 : 201);
        } finally {
            $lock->release();
        }
    }

    public function destroy(Request $request, MediaUpload $upload)
    {
        $this->authorizeUpload($request, $upload);

        $upload->discard();

        return response()->noContent();
    }

    private function authorizeUpload(Request $request, MediaUpload $upload): void
    {
        Gate::authorize($upload->media_file_id ? 'admin.media.replace' : 'admin.media.upload');

        abort_unless((int) $upload->user_id === (int) $request->user()->id, 404);
    }

    private function folder(?string $input): string
    {
        $segments = collect(explode('/', str_replace('\\', '/', (string) $input)))
            ->map(fn ($segment) => Str::slug($segment))
            ->filter()
            ->take(5)
            ->values();

        $folder = $segments->implode('/');

        return $folder === '' || app(MediaLibrary::class)->isExcluded(MediaFile::STORAGE, $folder)
            ? config('media.upload_folder')
            : $folder;
    }
}
