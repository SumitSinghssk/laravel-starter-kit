<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CommonStatusEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\TestimonialRequest;
use App\Models\Testimonial;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class TestimonialController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('admin.testimonials.view');

        $query = Testimonial::query();

        if ($request->filled('search') && is_string($request->search)) {
            $term = '%'.trim($request->search).'%';
            $query->where(fn ($q) => $q->where('name', 'like', $term)
                ->orWhere('company', 'like', $term)
                ->orWhere('designation', 'like', $term)
                ->orWhere('quote', 'like', $term));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('rating')) {
            $query->where('rating', $request->rating);
        }

        if ($request->filled('featured')) {
            $query->where('is_featured', $request->featured === 'yes');
        }

        $testimonials = $query->ordered()
            ->paginate(20)
            ->withQueryString();

        return view('admin.testimonials.index', compact('testimonials'));
    }

    public function create()
    {
        Gate::authorize('admin.testimonials.create');

        return view('admin.testimonials.create');
    }

    public function store(TestimonialRequest $request)
    {
        Gate::authorize('admin.testimonials.create');

        $data = $this->data($request);

        if ($request->hasFile('photo')) {
            $data['photo'] = $request->file('photo')->store('testimonials', 'public');
        }

        $testimonial = Testimonial::create($data);

        if (Gate::allows('admin.testimonials.edit')) {
            return to_route('admin.testimonials.edit', $testimonial)->with('success', 'Testimonial Created');
        }

        return to_route('admin.testimonials.index')->with('success', 'Testimonial Created');
    }

    public function edit(Testimonial $testimonial)
    {
        Gate::authorize('admin.testimonials.edit');

        return view('admin.testimonials.edit', compact('testimonial'));
    }

    public function update(TestimonialRequest $request, Testimonial $testimonial)
    {
        Gate::authorize('admin.testimonials.edit');

        $data = $this->data($request);

        if ($request->hasFile('photo')) {
            if ($testimonial->photo) {
                Storage::disk('public')->delete($testimonial->photo);
            }
            $data['photo'] = $request->file('photo')->store('testimonials', 'public');
        } elseif ($request->boolean('remove_image')) {
            if ($testimonial->photo) {
                Storage::disk('public')->delete($testimonial->photo);
            }
            $data['photo'] = null;
        }

        $testimonial->update($data);

        return back()->with('success', 'Testimonial Updated');
    }

    public function destroy(Testimonial $testimonial)
    {
        Gate::authorize('admin.testimonials.delete');

        $testimonial->delete();

        return to_route('admin.testimonials.index')->with('success', 'Testimonial deleted');
    }

    public function toggleStatus(Testimonial $testimonial)
    {
        Gate::authorize('admin.testimonials.toogle-status');

        $testimonial->status = $testimonial->status === CommonStatusEnum::ACTIVE ? CommonStatusEnum::INACTIVE : CommonStatusEnum::ACTIVE;
        $testimonial->save();

        return response()->json([
            'status' => $testimonial->status,
            'message' => 'Status updated successfully',
        ]);
    }

    private function data(TestimonialRequest $request): array
    {
        $data = $request->safe()->except(['photo', 'remove_image']);
        $data['is_featured'] = $request->boolean('is_featured');
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);

        return $data;
    }
}
