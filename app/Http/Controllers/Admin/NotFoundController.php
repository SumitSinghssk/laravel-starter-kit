<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RedirectRequest;
use App\Models\NotFoundLog;
use App\Models\Redirect;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class NotFoundController extends Controller
{
    public const SORTS = [
        'recent' => 'Most recent',
        'hits' => 'Most hits',
        'oldest' => 'First seen',
    ];

    public function index(Request $request)
    {
        Gate::authorize('admin.not-found.view');

        self::prune();

        $redirected = array_keys(Redirect::activeMap());
        $status = in_array($request->status, ['open', 'redirected', 'ignored', 'all'], true) ? $request->status : 'open';

        $counts = [
            'open' => $this->scoped('open', $redirected)->count(),
            'redirected' => $this->scoped('redirected', $redirected)->count(),
            'ignored' => $this->scoped('ignored', $redirected)->count(),
            'all' => NotFoundLog::count(),
        ];

        $query = $this->scoped($status, $redirected);

        if ($request->filled('search') && is_string($request->search)) {
            $term = '%'.str_replace(['%', '_'], ['\%', '\_'], trim($request->search)).'%';
            $query->where(fn ($q) => $q->where('path', 'like', $term)->orWhere('last_referrer', 'like', $term));
        }

        match ($request->sort) {
            'hits' => $query->orderByDesc('hits')->orderByDesc('last_seen_at'),
            'oldest' => $query->orderBy('first_seen_at'),
            default => $query->orderByDesc('last_seen_at'),
        };

        return view('admin.not-found.index', [
            'logs' => $query->paginate(config('not_found.per_page'))->withQueryString(),
            'counts' => $counts,
            'status' => $status,
            'redirected' => array_flip($redirected),
        ]);
    }

    public function redirect(RedirectRequest $request)
    {
        Gate::authorize('admin.redirects.create');

        $redirect = Redirect::create($request->validated());

        return back()->with('success', "Redirect created: {$redirect->source_path} → {$redirect->target_url}");
    }

    public function ignore(Request $request)
    {
        Gate::authorize('admin.not-found.manage');

        $ignore = $request->boolean('ignore', true);
        $count = NotFoundLog::whereKey($this->ids($request))->update(['ignored' => $ignore]);

        return back()->with('success', $count.' '.Str::plural('URL', $count).($ignore ? ' ignored.' : ' moved back to the open list.'));
    }

    public function destroy(Request $request)
    {
        Gate::authorize('admin.not-found.manage');

        $count = NotFoundLog::whereKey($this->ids($request))->delete();

        return back()->with('success', $count.' '.Str::plural('entry', $count).' removed. They come back if the URL is visited again.');
    }

    public function clear(Request $request)
    {
        Gate::authorize('admin.not-found.manage');

        $status = in_array($request->status, ['open', 'redirected', 'ignored', 'all'], true) ? $request->status : 'all';
        $count = $this->scoped($status, array_keys(Redirect::activeMap()))->delete();

        return to_route('admin.not-found.index', ['status' => $status])->with('success', $count.' '.Str::plural('entry', $count).' removed.');
    }

    public static function prune(): int
    {
        $days = (int) config('not_found.keep_days');

        return $days ? NotFoundLog::where('last_seen_at', '<', now()->subDays($days))->delete() : 0;
    }

    private function scoped(string $status, array $redirected): Builder
    {
        return match ($status) {
            'open' => NotFoundLog::where('ignored', false)->whereNotIn('path', $redirected),
            'redirected' => NotFoundLog::whereIn('path', $redirected ?: ['']),
            'ignored' => NotFoundLog::where('ignored', true)->whereNotIn('path', $redirected),
            default => NotFoundLog::query(),
        };
    }

    private function ids(Request $request): array
    {
        return $request->validate(['ids' => ['required', 'array', 'max:1000'], 'ids.*' => ['integer']])['ids'];
    }
}
