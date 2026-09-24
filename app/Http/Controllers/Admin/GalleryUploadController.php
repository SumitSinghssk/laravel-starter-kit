<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Gallery;
use App\Models\GalleryItem;
use App\Models\GalleryUpload;
use App\Services\GalleryMediaProcessor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Number;
use Illuminate\Validation\ValidationException;

class GalleryUploadController extends Controller
{
    public function store(Request $request, Gallery $gallery)
    {
        Gate::authorize('admin.galleries.edit');

        GalleryUpload::pruneStale();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'size' => ['required', 'integer', 'min:1'],
            'mime' => ['nullable', 'string', 'max:100'],
            'fingerprint' => ['required', 'string', 'max:255'],
        ]);

        $kind = $this->kindFor($validated['mime'] ?? '', $validated['name']);

        if (! $kind) {
            throw ValidationException::withMessages(['file' => 'Only JPG, PNG and WebP images or MP4, WebM and MOV videos can be uploaded.']);
        }

        $max = config("gallery.max_size.{$kind}");

        if ($validated['size'] > $max) {
            throw ValidationException::withMessages(['file' => ucfirst($kind).'s can be at most '.Number::fileSize($max).'. This file is '.Number::fileSize($validated['size']).'.']);
        }

        $fingerprint = hash('sha256', $validated['fingerprint']);

        $upload = GalleryUpload::where('gallery_id', $gallery->id)
            ->where('user_id', $request->user()->id)
            ->where('fingerprint', $fingerprint)
            ->where('size', $validated['size'])
            ->first();

        if (! $upload) {
            $chunkSize = config('gallery.chunk_size');

            $upload = GalleryUpload::create([
                'gallery_id' => $gallery->id,
                'user_id' => $request->user()->id,
                'fingerprint' => $fingerprint,
                'original_name' => $validated['name'],
                'kind' => $kind,
                'size' => $validated['size'],
                'chunk_size' => $chunkSize,
                'total_chunks' => (int) ceil($validated['size'] / $chunkSize),
            ]);
        }

        return response()->json([
            'id' => $upload->id,
            'kind' => $upload->kind,
            'chunk_size' => $upload->chunk_size,
            'total_chunks' => $upload->total_chunks,
            'received' => $upload->receivedChunks(),
            'chunk_url' => route('admin.gallery-uploads.chunk', [$upload, '__INDEX__']),
            'complete_url' => route('admin.gallery-uploads.complete', $upload),
            'cancel_url' => route('admin.gallery-uploads.destroy', $upload),
        ]);
    }

    public function chunk(Request $request, GalleryUpload $upload, int $index)
    {
        $this->authorizeUpload($request, $upload);

        abort_unless($index >= 0 && $index < $upload->total_chunks, 422, 'Unknown chunk.');

        $request->validate(['chunk' => ['required', 'file']]);
        $file = $request->file('chunk');

        if ($file->getSize() !== $upload->expectedChunkSize($index)) {
            throw ValidationException::withMessages(['chunk' => 'This chunk arrived incomplete. It will be sent again.']);
        }

        Storage::disk('local')->putFileAs($upload->chunkDirectory(), $file, (string) $index);
        $upload->touch();

        return response()->json(['index' => $index]);
    }

    public function complete(Request $request, GalleryUpload $upload, GalleryMediaProcessor $processor)
    {
        $this->authorizeUpload($request, $upload);

        $request->validate([
            'thumbnail' => ['nullable', 'file', 'max:2048'],
            'duration' => ['nullable', 'numeric', 'min:0', 'max:86400'],
            'width' => ['nullable', 'integer', 'min:1', 'max:20000'],
            'height' => ['nullable', 'integer', 'min:1', 'max:20000'],
        ]);

        $lock = Cache::lock('gallery-upload:'.$upload->id, 120);
        abort_unless($lock->get(), 409, 'This upload is already being finished.');

        try {
            if ($missing = $upload->missingChunks()) {
                return response()->json(['message' => 'Some chunks are missing.', 'missing' => $missing], 422);
            }

            $file = $upload->assemble();

            try {
                $attributes = $this->process($upload, $file, $request, $processor);
            } catch (ValidationException $e) {
                $upload->discard();
                throw $e;
            } finally {
                @unlink($file);
            }

            $gallery = $upload->gallery;
            $item = $gallery->items()->create([
                ...$attributes,
                'title' => $this->titleFromFileName($upload->original_name),
                'sort_order' => (int) GalleryItem::where('gallery_id', $gallery->id)->max('sort_order') + 1,
            ]);

            $upload->discard();

            return response()->json(['item' => $item->toAdminArray($gallery->cover_item_id)], 201);
        } finally {
            $lock->release();
        }
    }

    public function destroy(Request $request, GalleryUpload $upload)
    {
        $this->authorizeUpload($request, $upload);

        $upload->discard();

        return response()->noContent();
    }

    private function authorizeUpload(Request $request, GalleryUpload $upload): void
    {
        Gate::authorize('admin.galleries.edit');

        abort_unless((int) $upload->user_id === (int) $request->user()->id, 404);
    }

    private function process(GalleryUpload $upload, string $file, Request $request, GalleryMediaProcessor $processor): array
    {
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($file) ?: '';

        if (! array_key_exists($mime, config("gallery.mimes.{$upload->kind}"))) {
            throw ValidationException::withMessages(['file' => "This file's contents don't match a supported {$upload->kind} format."]);
        }

        if ($upload->kind === GalleryItem::IMAGE) {
            return $processor->storeImage($upload->gallery, $file, $mime);
        }

        return [
            ...$processor->storeVideo($upload->gallery, $file, $mime, $request->file('thumbnail')),
            'duration' => $request->filled('duration') ? (int) round((float) $request->duration) : null,
            'width' => $request->integer('width') ?: null,
            'height' => $request->integer('height') ?: null,
        ];
    }

    private function titleFromFileName(string $name): string
    {
        $title = trim(preg_replace('/[\s_\-.]+/', ' ', pathinfo($name, PATHINFO_FILENAME)));

        return mb_substr(ucfirst(mb_strtolower($title)), 0, 255) ?: 'Untitled';
    }

    private function kindFor(string $mime, string $name): ?string
    {
        foreach (config('gallery.mimes') as $kind => $types) {
            if (array_key_exists($mime, $types)) {
                return $kind;
            }
        }

        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));

        return match (true) {
            in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true) => GalleryItem::IMAGE,
            in_array($extension, ['mp4', 'm4v', 'webm', 'mov'], true) => GalleryItem::VIDEO,
            default => null,
        };
    }
}
