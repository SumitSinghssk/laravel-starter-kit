<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('admin.notifications.view');

        $notifications = Notification::latest()
            ->take(10)
            ->get()
            ->map(function ($n) {
                return [
                    'id' => $n->id,
                    'type' => $n->type,
                    'title' => $n->title,
                    'message' => $n->message,
                    'url' => $n->url,
                    'seen' => ! is_null($n->seen_at),
                    'time' => $n->created_at->diffForHumans(),
                ];
            });

        return response()->json([
            'notifications' => $notifications,
            'unread_count' => Notification::whereNull('seen_at')->count(),
        ]);
    }

    public function list()
    {
        Gate::authorize('admin.notifications.view');

        $notifications = Notification::orderByRaw('seen_at IS NULL DESC')->latest()->paginate(15);

        return view('admin.notifications.index', compact('notifications'));
    }

    public function view(Request $request, $id)
    {
        Gate::authorize('admin.notifications.view');

        $notification = Notification::findOrFail($id);

        if (is_null($notification->seen_at)) {
            $notification->update([
                'seen_at' => now(),
            ]);
        }

        $target = $notification->url ?? route('admin.dashboard');

        $host = parse_url($target, PHP_URL_HOST);
        if ($host !== null && $host !== $request->getHost()) {
            $target = route('admin.dashboard');
        }

        return redirect($target);
    }

    public function markAllRead(Request $request)
    {
        Gate::authorize('admin.notifications.mark-all-as-read');

        Notification::whereNull('seen_at')->update(['seen_at' => now()]);

        return response()->json(['success' => true]);
    }

    public function markRead(Request $request, $id)
    {
        Gate::authorize('admin.notifications.view');

        $notification = Notification::findOrFail($id);

        if (is_null($notification->seen_at)) {
            $notification->update(['seen_at' => now()]);
        }

        return response()->json(['success' => true]);
    }
}
