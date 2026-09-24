<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CommonStatusEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PageStoreRequest;
use App\Http\Requests\Admin\PageUpdateRequest;
use App\Models\Page;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class PageController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('admin.pages.view');

        $query = Page::with('user');

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('title', 'like', '%'.$request->search.'%')
                    ->orWhere('content', 'like', '%'.$request->search.'%');
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $pages = $query->latest()
            ->paginate(20)
            ->withQueryString();

        return view('admin.pages.index', compact('pages'));
    }

    public function create()
    {
        Gate::authorize('admin.pages.create');

        return view('admin.pages.create');
    }

    public function store(PageStoreRequest $request)
    {
        Gate::authorize('admin.pages.create');

        $data = $request->validated();

        $data['user_id'] = Auth::id();
        $data['slug'] = $data['slug'] ?? str()->slug($data['title']);

        if ($request->hasFile('featured_image')) {
            $data['featured_image'] = $request->file('featured_image')->store('pages', 'public');
        }

        $page = Page::create($data);

        if (Gate::allows('admin.pages.edit')) {
            return to_route('admin.pages.edit', $page)->with('success', 'Page Created');
        }

        return to_route('admin.pages.index')->with('success', 'Page Created');
    }

    public function edit(Page $page)
    {
        Gate::authorize('admin.pages.edit');

        $page->load('seo');

        return view('admin.pages.edit', compact('page'));
    }

    public function update(PageUpdateRequest $request, Page $page)
    {
        Gate::authorize('admin.pages.edit');

        $data = $request->validated();

        if ($request->hasFile('featured_image')) {
            if ($page->featured_image) {
                Storage::disk('public')->delete($page->featured_image);
            }
            $data['featured_image'] = $request->file('featured_image')->store('pages', 'public');
        } elseif ($request->boolean('remove_image')) {
            if ($page->featured_image) {
                Storage::disk('public')->delete($page->featured_image);
            }
            $data['featured_image'] = null;
        }

        unset($data['remove_image']);

        $page->update($data);

        return back()->with('success', 'Page Updated');
    }

    public function destroy(Page $page)
    {
        Gate::authorize('admin.pages.delete');

        $page->delete();

        return to_route('admin.pages.index')->with('success', 'Page deleted');
    }

    public function toggleStatus(Page $page)
    {
        Gate::authorize('admin.pages.toogle-status');

        $page->status = $page->status === CommonStatusEnum::ACTIVE ? CommonStatusEnum::INACTIVE : CommonStatusEnum::ACTIVE;
        $page->save();

        return response()->json([
            'status' => $page->status,
            'message' => 'Status updated successfully',
        ]);
    }
}
