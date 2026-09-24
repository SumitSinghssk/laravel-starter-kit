<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CommonStatusEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\GalleryRequest;
use App\Models\Gallery;
use App\Models\GalleryItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class GalleryController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('admin.galleries.view');

        $query = Gallery::query()
            ->with(['coverItem', 'firstItem'])
            ->withCount([
                'items',
                'items as images_count' => fn ($q) => $q->where('type', GalleryItem::IMAGE),
                'items as videos_count' => fn ($q) => $q->whereIn('type', [GalleryItem::VIDEO, GalleryItem::YOUTUBE]),
            ]);

        if ($request->filled('search') && is_string($request->search)) {
            $term = '%'.trim($request->search).'%';
            $query->where(fn ($q) => $q->where('title', 'like', $term)->orWhere('description', 'like', $term));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('featured')) {
            $query->where('is_featured', $request->featured === 'yes');
        }

        $galleries = $query->ordered()->paginate(24)->withQueryString();

        return view('admin.galleries.index', compact('galleries'));
    }

    public function create()
    {
        Gate::authorize('admin.galleries.create');

        return view('admin.galleries.create');
    }

    public function store(GalleryRequest $request)
    {
        Gate::authorize('admin.galleries.create');

        $gallery = Gallery::create($request->albumData());

        if (Gate::allows('admin.galleries.edit')) {
            return to_route('admin.galleries.edit', $gallery)->with('success', 'Album created. Now add photos and videos.');
        }

        return to_route('admin.galleries.index')->with('success', 'Album Created');
    }

    public function edit(Gallery $gallery)
    {
        Gate::authorize('admin.galleries.edit');

        $gallery->loadCount('items');

        return view('admin.galleries.edit', compact('gallery'));
    }

    public function update(GalleryRequest $request, Gallery $gallery)
    {
        Gate::authorize('admin.galleries.edit');

        $gallery->update($request->albumData());

        return back()->with('success', 'Album Updated');
    }

    public function destroy(Gallery $gallery)
    {
        Gate::authorize('admin.galleries.delete');

        $gallery->delete();

        return to_route('admin.galleries.index')->with('success', 'Album and its media deleted');
    }

    public function toggleStatus(Gallery $gallery)
    {
        Gate::authorize('admin.galleries.toogle-status');

        $gallery->status = $gallery->status === CommonStatusEnum::ACTIVE ? CommonStatusEnum::INACTIVE : CommonStatusEnum::ACTIVE;
        $gallery->save();

        return response()->json([
            'status' => $gallery->status,
            'message' => 'Status updated successfully',
        ]);
    }
}
