<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CommonStatusEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RedirectRequest;
use App\Models\Redirect;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class RedirectController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('admin.redirects.view');

        $query = Redirect::query();

        if ($request->filled('search') && is_string($request->search)) {
            $term = '%'.trim($request->search).'%';
            $query->where(fn ($q) => $q->where('source_path', 'like', $term)
                ->orWhere('target_url', 'like', $term)
                ->orWhere('note', 'like', $term));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('type')) {
            $query->where('status_code', $request->type);
        }

        $redirects = $query->latest()
            ->paginate(20)
            ->withQueryString();

        return view('admin.redirects.index', compact('redirects'));
    }

    public function create()
    {
        Gate::authorize('admin.redirects.create');

        return view('admin.redirects.create');
    }

    public function store(RedirectRequest $request)
    {
        Gate::authorize('admin.redirects.create');

        $redirect = Redirect::create($request->validated());

        if (Gate::allows('admin.redirects.edit')) {
            return to_route('admin.redirects.edit', $redirect)->with('success', 'Redirect Created');
        }

        return to_route('admin.redirects.index')->with('success', 'Redirect Created');
    }

    public function edit(Redirect $redirect)
    {
        Gate::authorize('admin.redirects.edit');

        return view('admin.redirects.edit', compact('redirect'));
    }

    public function update(RedirectRequest $request, Redirect $redirect)
    {
        Gate::authorize('admin.redirects.edit');

        $redirect->update($request->validated());

        return back()->with('success', 'Redirect Updated');
    }

    public function destroy(Redirect $redirect)
    {
        Gate::authorize('admin.redirects.delete');

        $redirect->delete();

        return to_route('admin.redirects.index')->with('success', 'Redirect deleted');
    }

    public function toggleStatus(Redirect $redirect)
    {
        Gate::authorize('admin.redirects.toogle-status');

        $redirect->status = $redirect->status === CommonStatusEnum::ACTIVE ? CommonStatusEnum::INACTIVE : CommonStatusEnum::ACTIVE;
        $redirect->save();

        return response()->json([
            'status' => $redirect->status,
            'message' => 'Status updated successfully',
        ]);
    }
}
