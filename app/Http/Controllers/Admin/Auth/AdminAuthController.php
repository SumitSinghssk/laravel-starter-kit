<?php

namespace App\Http\Controllers\Admin\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\ActivityLogger;
use App\Services\AdminLogin;
use App\Services\TwoFactor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdminAuthController extends Controller
{
    public function index()
    {
        return view('admin.auth.login');
    }

    public function store(LoginRequest $request, TwoFactor $twoFactor, AdminLogin $login): RedirectResponse
    {
        $request->authenticate('web');

        $user = Auth::user();
        $remember = $request->boolean('remember');

        if ($user->hasTwoFactor() && ! $twoFactor->trusts($request, $user)) {
            Auth::guard('web')->logout();
            $request->session()->regenerate();
            $twoFactor->startLogin($request, $user, $remember);

            return to_route('admin.two-factor.challenge');
        }

        $login->complete($request, $user, $remember, $user->hasTwoFactor() ? 'trusted device' : null);

        return redirect()->intended(route('admin.dashboard'));
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
