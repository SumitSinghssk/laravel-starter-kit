<?php

namespace App\Http\Controllers\Admin\Auth;

use App\Http\Controllers\Controller;
use App\Services\ActiveSessions;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class SessionController extends Controller
{
    public function __construct(private ActiveSessions $sessions) {}

    public function destroy(Request $request, string $key)
    {
        Gate::authorize('profile.update');
        $this->confirmPassword($request);

        $ended = $this->sessions->end($request->user(), $key, $request->session()->getId());
        $this->keepThisDeviceRemembered($request);

        if (! $ended) {
            return back()->with('warning', 'That device was already signed out.');
        }

        ActivityLogger::log('logout', description: 'Signed out another device');

        return back()->with('success', 'That device has been signed out.');
    }

    public function destroyOthers(Request $request)
    {
        Gate::authorize('profile.update');
        $this->confirmPassword($request);

        $count = $this->sessions->endAll($request->user(), $request->session()->getId());
        $this->keepThisDeviceRemembered($request);

        ActivityLogger::log('logout', description: "Signed out {$count} other ".Str::plural('device', $count));

        return back()->with('success', $count ? "Signed out of {$count} other ".Str::plural('device', $count).'.' : 'No other devices were signed in.');
    }

    private function confirmPassword(Request $request): void
    {
        $request->validateWithBag('sessions', ['password' => ['required', 'current_password:web']], [
            'password.current_password' => 'That password is not correct.',
        ]);
    }

    private function keepThisDeviceRemembered(Request $request): void
    {
        $guard = Auth::guard('web');

        if ($request->hasCookie($guard->getRecallerName())) {
            $guard->login($request->user(), true);
        }
    }
}
