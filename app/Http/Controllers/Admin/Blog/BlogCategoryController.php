<?php

namespace App\Http\Controllers\Admin\Blog;

use App\Enums\CommonStatusEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BlogCategoryStoreRequest;
use App\Http\Requests\Admin\BlogCategoryUpdateRequest;
use App\Models\BlogCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class BlogCategoryController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('admin.blog-categories.view');

        $query = BlogCategory::with('parent');

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', '%'.$request->search.'%')
                    ->orWhere('description', 'like', '%'.$request->search.'%');
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $categories = $query->orderByRaw('CASE WHEN parent_id IS NULL THEN 0 ELSE 1 END')
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('admin.blog-categories.index', compact('categories'));
    }

    public function create()
    {
        Gate::authorize('admin.blog-categories.create');

        $parentCategories = BlogCategory::whereNull('parent_id')
            ->where('status', 'active')
            ->orderBy('name')
            ->get();

        return view('admin.blog-categories.create', compact('parentCategories'));
    }

    public function store(BlogCategoryStoreRequest $request)
    {
        Gate::authorize('admin.blog-categories.create');

        $data = $request->validated();

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('blog-categories', 'public');
        }

        $data['slug'] = $data['slug'] ?? str()->slug($data['name']);

        $category = BlogCategory::create($data);

        if (Gate::allows('admin.blog-categories.edit')) {
            return to_route('admin.blog-categories.edit', $category)->with('success', 'Blog Category Created');
        }

        return to_route('admin.blog-categories.index')->with('success', 'Blog Category Created');
    }

    public function edit(BlogCategory $blogCategory)
    {
        Gate::authorize('admin.blog-categories.edit');

        $blogCategory->load('seo');

        $parentCategories = BlogCategory::whereNull('parent_id')
            ->where('status', 'active')
            ->where('id', '!=', $blogCategory->id)
            ->orderBy('name')
            ->get();

        return view('admin.blog-categories.edit', compact('blogCategory', 'parentCategories'));
    }

    public function update(BlogCategoryUpdateRequest $request, BlogCategory $blogCategory)
    {
        Gate::authorize('admin.blog-categories.edit');

        $data = $request->validated();

        if ($request->hasFile('image')) {
            if ($blogCategory->image) {
                Storage::disk('public')->delete($blogCategory->image);
            }
            $data['image'] = $request->file('image')->store('blog-categories', 'public');
        } elseif ($request->boolean('remove_image')) {
            if ($blogCategory->image) {
                Storage::disk('public')->delete($blogCategory->image);
            }
            $data['image'] = null;
        }

        $blogCategory->update($data);

        return back()->with('success', 'Blog Category Updated');
    }

    public function destroy(BlogCategory $blogCategory)
    {
        Gate::authorize('admin.blog-categories.delete');

        $blogCategory->delete();

        return to_route('admin.blog-categories.index')->with('success', 'Blog Category deleted');
    }

    public function toggleStatus(BlogCategory $blogCategory)
    {
        Gate::authorize('admin.blog-categories.toogle-status');

        $blogCategory->status = $blogCategory->status === CommonStatusEnum::ACTIVE ? CommonStatusEnum::INACTIVE : CommonStatusEnum::ACTIVE;
        $blogCategory->save();

        return response()->json([
            'status' => $blogCategory->status,
            'message' => 'Status updated successfully',
        ]);
    }
}
