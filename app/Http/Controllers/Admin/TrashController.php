<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Trash\TrashManager;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class TrashController extends Controller
{
    public function __construct(private TrashManager $trash) {}

    public function index(Request $request)
    {
        Gate::authorize('admin.trash.view');

        $this->trash->purge();

        $user = $request->user();
        $types = $this->trash->typesFor($user);
        $type = array_key_exists((string) $request->type, $types) ? $request->type : null;
        $sort = $request->sort === 'oldest' ? 'oldest' : 'newest';
        $search = is_string($request->search) ? $request->search : null;

        $rows = $this->trash->items($user, $type, $search, $sort);
        $perPage = config('trash.per_page');
        $page = LengthAwarePaginator::resolveCurrentPage();

        $items = new LengthAwarePaginator(
            $rows->forPage($page, $perPage)->values(),
            $rows->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()],
        );

        return view('admin.trash.index', [
            'items' => $items,
            'types' => $types,
            'counts' => $this->trash->counts($user),
            'type' => $type,
            'sort' => $sort,
        ]);
    }

    public function restore(Request $request)
    {
        Gate::authorize('admin.trash.restore');

        $result = $this->trash->restore($request->user(), $this->keys($request));

        return $this->finish($result, 'restored');
    }

    public function destroy(Request $request)
    {
        Gate::authorize('admin.trash.delete');

        $result = $this->trash->forceDelete($request->user(), $this->keys($request));

        return $this->finish($result, 'permanently deleted');
    }

    public function empty(Request $request)
    {
        Gate::authorize('admin.trash.delete');

        $type = $request->validate(['type' => ['nullable', 'string']])['type'] ?? null;
        $result = $this->trash->forceDelete($request->user(), $this->trash->allKeys($request->user(), $type));

        return $this->finish($result, 'permanently deleted', redirectToIndex: true);
    }

    private function keys(Request $request): array
    {
        return $request->validate([
            'items' => ['required', 'array', 'max:500'],
            'items.*' => ['string', 'max:64'],
        ])['items'];
    }

    private function finish(array $result, string $verb, bool $redirectToIndex = false)
    {
        $response = $redirectToIndex ? to_route('admin.trash.index', request()->only('type')) : back();

        if ($result['count'] === 0 && $result['notes'] === []) {
            return $response->with('warning', 'Nothing was changed. The items may already have been restored or deleted.');
        }

        if ($result['count']) {
            $response->with('success', $result['count'].' '.Str::plural('item', $result['count'])." {$verb}.");
        }

        if ($result['notes']) {
            $response->with($result['count'] ? 'info' : 'error', implode(' ', $result['notes']));
        }

        return $response;
    }
}
