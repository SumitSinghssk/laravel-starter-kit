<?php

namespace App\Http\Controllers\Admin\Setting;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ActivityLogController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('admin.activity-logs.view');

        $logs = ActivityLog::with('user:id,name,email')
            ->when($request->search, function ($q) use ($request) {
                $term = '%'.addcslashes($request->search, '%_\\').'%';

                $q->where(function ($w) use ($term) {
                    $w->where('description', 'like', $term)
                        ->orWhere('page_title', 'like', $term)
                        ->orWhere('url', 'like', $term)
                        ->orWhereHas('user', fn ($u) => $u->where('name', 'like', $term)
                            ->orWhere('email', 'like', $term)
                        );
                });
            })
            ->when($request->action, fn ($q) => $q->where('action', $request->action))
            ->when($request->user_id, fn ($q) => $q->where('user_id', $request->user_id))
            ->latest()
            ->paginate(10)
            ->withQueryString();

        $stats = [
            'total' => ActivityLog::count(),
            'logins' => ActivityLog::where('action', 'login')->count(),
            'failed' => ActivityLog::where('action', 'failed_login')->count(),
            'views' => ActivityLog::where('action', 'viewed')->count(),
            'crud' => ActivityLog::whereIn('action', ['created', 'updated', 'deleted'])->count(),
            'suspicious' => ActivityLog::where('is_suspicious', true)->count(),
        ];

        $users = User::select('id', 'name', 'email')->orderBy('name')->get();

        return view('admin.activity-logs.index', compact('logs', 'stats', 'users'));
    }

    public function show(ActivityLog $activityLog)
    {
        Gate::authorize('admin.activity-logs.view');

        $activityLog->load('user:id,name,email');

        return view('admin.activity-logs.show', ['log' => $activityLog]);
    }

    public function clear()
    {
        Gate::authorize('admin.activity-logs.clear');

        ActivityLog::truncate();

        return back()->with('success', 'All activity logs have been cleared.');
    }
}
