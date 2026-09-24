<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SeoStoreRequest;
use App\Http\Requests\Admin\SeoUpdateRequest;
use App\Models\Blog;
use App\Models\BlogCategory;
use App\Models\Page;
use App\Models\Seo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class SeoController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('admin.seo.view');

        $seos = Seo::query()
            ->when($request->search, function ($q) use ($request) {
                $term = '%'.addcslashes($request->search, '%_\\').'%';
                $q->where(function ($q) use ($term) {
                    $q->where('page', 'like', $term)
                        ->orWhere('slug', 'like', $term)
                        ->orWhere('meta_title', 'like', $term);
                });
            })
            ->when($request->index !== null && $request->index !== '', fn ($q) => $q->where('index', (bool) $request->index)
            )
            ->orderByRaw("
                CASE 
                    WHEN slug = 'default-seo' THEN 0 
                    ELSE 1 
                END
            ")
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('admin.seo.index', compact('seos'));
    }

    public function create(Request $request)
    {
        Gate::authorize('admin.seo.create');

        $modelType = $request->model_type;
        $modelId = $request->model_id;

        $defaultData = [];

        if ($modelType === 'blog_category' && $modelId) {
            $category = BlogCategory::find($modelId);

            if ($category) {
                $defaultData = [
                    'page' => $category->name,
                    'slug' => $category->slug,
                ];
            }
        }

        if ($modelType === 'blog' && $modelId) {
            $blog = Blog::find($modelId);

            if ($blog) {
                $defaultData = [
                    'page' => $blog->title,
                    'slug' => $blog->slug,
                ];
            }
        }

        if ($modelType === 'page' && $modelId) {
            $page = Page::find($modelId);

            if ($page) {
                $defaultData = [
                    'page' => $page->title,
                    'slug' => $page->slug,
                ];
            }
        }

        return view('admin.seo.create', compact('defaultData', 'modelType', 'modelId'));
    }

    public function store(SeoStoreRequest $request)
    {
        Gate::authorize('admin.seo.create');

        $data = $request->validated();

        if ($request->hasFile('og_image')) {
            $data['og_image'] = $request->file('og_image')->store('seo', 'public');
        }

        if (array_key_exists('faqs', $data)) {
            if ($request->filled('faqs')) {
                $faqs = json_decode($request->faqs, true);

                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw ValidationException::withMessages([
                        'faqs' => 'The FAQ data is invalid.',
                    ]);
                }

                $data['faqs'] = $faqs;
            } else {
                $data['faqs'] = null;
            }
        }

        $seo = Seo::create($data);

        if (Gate::allows('admin.seo.edit')) {
            return to_route('admin.seo.edit', $seo)->with('success', 'SEO Created');
        }

        return to_route('admin.seo.index')->with('success', 'SEO Created');
    }

    public function edit(Seo $seo)
    {
        Gate::authorize('admin.seo.edit');

        return view('admin.seo.edit', compact('seo'));
    }

    public function update(SeoUpdateRequest $request, Seo $seo)
    {
        Gate::authorize('admin.seo.edit');

        $data = $request->validated();

        if ($request->hasFile('og_image')) {
            if ($seo->og_image) {
                Storage::disk('public')->delete($seo->og_image);
            }
            $data['og_image'] = $request->file('og_image')->store('seo', 'public');

        } elseif ($request->boolean('remove_og_image')) {
            if ($seo->og_image) {
                Storage::disk('public')->delete($seo->og_image);
            }
            $data['og_image'] = null;
        }

        if (array_key_exists('faqs', $data)) {
            if ($request->filled('faqs')) {
                $faqs = json_decode($request->faqs, true);

                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw ValidationException::withMessages([
                        'faqs' => 'The FAQ data is invalid.',
                    ]);
                }

                $data['faqs'] = $faqs;
            } else {
                $data['faqs'] = null;
            }
        }

        $seo->update($data);

        return back()->with('success', 'SEO Updated');
    }

    public function destroy(Seo $seo)
    {
        Gate::authorize('admin.seo.delete');

        if ($seo->og_image) {
            Storage::disk('public')->delete($seo->og_image);
        }

        $seo->delete();

        return to_route('admin.seo.index')->with('success', 'SEO deleted');
    }
}
