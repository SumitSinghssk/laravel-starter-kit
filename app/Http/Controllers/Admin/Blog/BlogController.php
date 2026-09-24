<?php

namespace App\Http\Controllers\Admin\Blog;

use App\Enums\CommonStatusEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BlogStoreRequest;
use App\Http\Requests\Admin\BlogUpdateRequest;
use App\Models\Blog;
use App\Models\BlogCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class BlogController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('admin.blogs.view');

        $query = Blog::with(['author', 'categories']);

        if ($request->filled('search')) {
            $term = '%'.addcslashes($request->search, '%_\\').'%';
            $query->where(function ($q) use ($term) {
                $q->where('title', 'like', $term)
                    ->orWhere('excerpt', 'like', $term);
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('category_id')) {
            $query->whereHas('categories', function ($q) use ($request) {
                $q->where('blog_categories.id', $request->category_id);
            });
        }

        $blogs = $query->latest()
            ->paginate(20)
            ->withQueryString();

        $categories = BlogCategory::orderBy('name')->get();

        return view('admin.blogs.index', compact('blogs', 'categories'));
    }

    public function create()
    {
        Gate::authorize('admin.blogs.create');

        $categories = BlogCategory::whereNull('parent_id')
            ->where('status', 'active')
            ->with(['children' => fn ($q) => $q->where('status', 'active')->orderBy('name')])
            ->orderBy('name')
            ->get();

        return view('admin.blogs.create', compact('categories'));
    }

    public function store(BlogStoreRequest $request)
    {
        Gate::authorize('admin.blogs.create');

        $data = $request->validated();

        $data['user_id'] = Auth::id();
        $data['slug'] = $data['slug'] ?? str()->slug($data['title']);

        if ($request->hasFile('featured_image')) {
            $data['featured_image'] = $request->file('featured_image')->store('blogs', 'public');
        }

        $categoryIds = $data['category_ids'] ?? [];
        unset($data['category_ids']);

        $blog = Blog::create($data);
        $blog->categories()->sync($categoryIds);

        if (Gate::allows('admin.blogs.edit')) {
            return to_route('admin.blogs.edit', $blog)->with('success', 'Blog Post Created');
        }

        return to_route('admin.blogs.index')->with('success', 'Blog Post Created');
    }

    public function edit(Blog $blog)
    {
        Gate::authorize('admin.blogs.edit');

        $blog->load(['seo', 'categories']);

        $categories = BlogCategory::whereNull('parent_id')
            ->where('status', 'active')
            ->with(['children' => fn ($q) => $q->where('status', 'active')->orderBy('name')])
            ->orderBy('name')
            ->get();

        $selectedCategoryIds = $blog->categories->pluck('id')->toArray();

        return view('admin.blogs.edit', compact('blog', 'categories', 'selectedCategoryIds'));
    }

    public function update(BlogUpdateRequest $request, Blog $blog)
    {
        Gate::authorize('admin.blogs.edit');

        $data = $request->validated();

        if ($request->hasFile('featured_image')) {
            if ($blog->featured_image) {
                Storage::disk('public')->delete($blog->featured_image);
            }
            $data['featured_image'] = $request->file('featured_image')->store('blogs', 'public');
        } elseif ($request->boolean('remove_image')) {
            if ($blog->featured_image) {
                Storage::disk('public')->delete($blog->featured_image);
            }
            $data['featured_image'] = null;
        }

        $categoryIds = $data['category_ids'] ?? [];
        unset($data['category_ids']);
        unset($data['remove_image']);

        $blog->update($data);
        $blog->categories()->sync($categoryIds);

        return back()->with('success', 'Blog Post Updated');
    }

    public function destroy(Blog $blog)
    {
        Gate::authorize('admin.blogs.delete');

        $blog->delete();

        return to_route('admin.blogs.index')->with('success', 'Blog Post deleted');
    }

    public function toggleStatus(Blog $blog)
    {
        Gate::authorize('admin.blogs.toogle-status');

        $blog->status = $blog->status === CommonStatusEnum::ACTIVE ? CommonStatusEnum::INACTIVE : CommonStatusEnum::ACTIVE;
        $blog->save();

        return response()->json([
            'status' => $blog->status,
            'message' => 'Status updated successfully',
        ]);
    }
}
