<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Gallery;
use App\Models\GalleryItem;
use App\Services\GalleryMediaProcessor;
use App\Support\YouTube;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class GalleryMediaController extends Controller
{
    public function index(Gallery $gallery)
    {
        Gate::authorize('admin.galleries.edit');

        $items = $gallery->items()->cursorPaginate(config('gallery.per_page'));

        return response()->json([
            'data' => $items->getCollection()->map(fn (GalleryItem $item) => $item->toAdminArray($gallery->cover_item_id)),
            'next_page_url' => $items->nextPageUrl(),
            'total' => $gallery->items()->count(),
        ]);
    }

    public function youtube(Request $request, Gallery $gallery)
    {
        Gate::authorize('admin.galleries.edit');

        $validated = $request->validate([
            'url' => ['required', 'string', 'max:2000'],
            'title' => ['nullable', 'string', 'max:255'],
        ]);

        $id = YouTube::videoId($validated['url']);

        if (! $id) {
            throw ValidationException::withMessages(['url' => 'Paste a YouTube video link (youtube.com/watch?v=…, youtu.be/…, Shorts) or its embed code.']);
        }

        if ($gallery->items()->where('youtube_id', $id)->exists()) {
            throw ValidationException::withMessages(['url' => 'This video is already in the album.']);
        }

        $item = $gallery->items()->create([
            'type' => GalleryItem::YOUTUBE,
            'youtube_id' => $id,
            'title' => $validated['title'] ?? $this->youtubeTitle($id),
            'sort_order' => (int) GalleryItem::where('gallery_id', $gallery->id)->max('sort_order') + 1,
        ]);

        return response()->json(['item' => $item->toAdminArray($gallery->cover_item_id)], 201);
    }

    public function update(Request $request, GalleryItem $item, GalleryMediaProcessor $processor)
    {
        Gate::authorize('admin.galleries.edit');

        $validated = $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'caption' => ['nullable', 'string', 'max:1000'],
            'thumbnail' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remove_thumbnail' => ['nullable', 'boolean'],
        ]);

        $item->fill(['title' => $validated['title'] ?? null, 'caption' => $validated['caption'] ?? null]);

        if ($item->type !== GalleryItem::IMAGE) {
            $old = $item->thumbnail_path;

            if ($request->hasFile('thumbnail')) {
                $item->thumbnail_path = $processor->storeThumbnail($item->gallery, $request->file('thumbnail'));
            } elseif ($request->boolean('remove_thumbnail')) {
                $item->thumbnail_path = null;
            }

            if ($old && $old !== $item->thumbnail_path) {
                GalleryItem::disk()->delete($old);
            }
        }

        $item->save();

        return response()->json(['item' => $item->toAdminArray($item->gallery->cover_item_id)]);
    }

    public function destroy(GalleryItem $item)
    {
        Gate::authorize('admin.galleries.edit');

        $item->delete();

        return response()->json(['deleted' => [$item->id]]);
    }

    public function bulkDestroy(Request $request, Gallery $gallery)
    {
        Gate::authorize('admin.galleries.edit');

        $validated = $request->validate(['ids' => ['required', 'array', 'max:500'], 'ids.*' => ['integer']]);

        $items = $gallery->items()->whereKey($validated['ids'])->get();
        $items->each->delete();

        return response()->json(['deleted' => $items->modelKeys()]);
    }

    public function reorder(Request $request, Gallery $gallery)
    {
        Gate::authorize('admin.galleries.edit');

        $validated = $request->validate(['ids' => ['required', 'array', 'max:5000'], 'ids.*' => ['integer', 'distinct']]);

        $current = GalleryItem::where('gallery_id', $gallery->id)->whereKey($validated['ids'])->pluck('sort_order', 'id');
        $ids = array_values(array_filter($validated['ids'], fn ($id) => $current->has($id)));
        $slots = $current->values()->sort()->values();

        if ($slots->unique()->count() !== $slots->count()) {
            $slots = collect(range((int) $slots->first(), (int) $slots->first() + count($ids) - 1));
        }

        DB::transaction(function () use ($ids, $slots) {
            foreach ($ids as $position => $id) {
                GalleryItem::whereKey($id)->update(['sort_order' => $slots[$position]]);
            }
        });

        return response()->json(['ok' => true]);
    }

    public function cover(Request $request, Gallery $gallery)
    {
        Gate::authorize('admin.galleries.edit');

        $validated = $request->validate(['item_id' => ['nullable', 'integer']]);
        $itemId = $validated['item_id'] ?? null;

        abort_if($itemId && ! $gallery->items()->whereKey($itemId)->exists(), 422, 'This item is not in the album.');

        $gallery->forceFill(['cover_item_id' => $itemId])->save();

        return response()->json(['cover_item_id' => $itemId]);
    }

    public function stream(GalleryItem $item)
    {
        Gate::authorize('admin.galleries.view');

        abort_unless($item->type === GalleryItem::VIDEO && $item->path && GalleryItem::disk()->exists($item->path), 404);

        return response()->file(GalleryItem::disk()->path($item->path), [
            'Content-Type' => $item->mime,
            'Cache-Control' => 'private, max-age=86400',
        ]);
    }

    private function youtubeTitle(string $id): ?string
    {
        return rescue(function () use ($id) {
            $title = Http::timeout(4)
                ->get('https://www.youtube.com/oembed', ['url' => "https://www.youtube.com/watch?v={$id}", 'format' => 'json'])
                ->json('title');

            return is_string($title) ? mb_substr($title, 0, 255) : null;
        }, null, report: false);
    }
}
