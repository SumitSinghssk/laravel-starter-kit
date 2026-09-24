<?php

namespace App\Http\Controllers\Admin\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdminAuthController extends Controller
{
    public function index()
    {
        return view('admin.auth.login');
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate('web');
        $request->session()->regenerate();
        $request->session()->put('auth_signed_in_at', now()->timestamp);

        $user = Auth::user();

        ActivityLogger::login($user, $request);

        notify(
            'Admin Login',
            'New Login',
            "{$user->name} logged into admin panel",
            [
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ],
            route('admin.users.edit', $user)
        );

        return to_route('admin.dashboard');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $guard = 'web';

        ActivityLogger::logout(Auth::guard($guard)->user(), $request);

        Auth::guard($guard)->logout();

        $request->session()->forget('login_'.sha1($guard));
        $request->session()->forget('password_hash_'.$guard);

        $request->session()->regenerateToken();

        return to_route('admin.login');
    }
}
