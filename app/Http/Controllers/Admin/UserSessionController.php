<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ActiveSessions;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class UserSessionController extends Controller
{
    private const PROTECTED_ROLES = ['super admin', 'super-admin'];

    public function __construct(private ActiveSessions $sessions) {}

    public function destroy(Request $request, User $user, string $key)
    {
        $this->authorizeFor($request, $user);

        if (! $this->sessions->end($user, $key, $request->session()->getId())) {
            return back()->with('warning', 'That session has already ended.');
        }

        ActivityLogger::log('logout', $user, description: "Signed {$user->name} out of a device");

        return back()->with('success', "{$user->name} has been signed out of that device.");
    }

    public function destroyAll(Request $request, User $user)
    {
        $this->authorizeFor($request, $user);

        $count = $this->sessions->endAll($user, $user->is($request->user()) ? $request->session()->getId() : null);

        ActivityLogger::log('logout', $user, description: "Signed {$user->name} out everywhere");

        return back()->with('success', $count
            ? "{$user->name} has been signed out of {$count} ".Str::plural('device', $count).'.'
            : "{$user->name} wasn't signed in anywhere. \"Remember me\" has been reset anyway.");
    }

    private function authorizeFor(Request $request, User $user): void
    {
        Gate::authorize('admin.users.sessions');

        abort_if($user->hasRole(self::PROTECTED_ROLES) && ! $request->user()->hasRole(self::PROTECTED_ROLES), 403, 'Only a super admin can sign out a super admin.');
    }
}
