<?php

namespace App\Http\Controllers\Admin\Auth;

use App\Http\Controllers\Controller;
use App\Services\AccountLockout;
use App\Services\AdminLogin;
use App\Services\TwoFactor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class TwoFactorChallengeController extends Controller
{
    private const MAX_ATTEMPTS = 5;

    public function __construct(private TwoFactor $twoFactor) {}

    public function create(Request $request)
    {
        $pending = $this->twoFactor->pendingLogin($request);

        if (! $pending) {
            return to_route('admin.login')->with('error', 'Your sign-in timed out. Enter your password again.');
        }

        return view('admin.auth.two-factor-challenge', [
            'email' => $pending['user']->email,
            'trustDays' => TwoFactor::TRUST_DAYS,
            'codesLeft' => $this->twoFactor->remainingCodes($pending['user']),
        ]);
    }

    public function store(Request $request, AdminLogin $login, AccountLockout $lockout): RedirectResponse
    {
        $pending = $this->twoFactor->pendingLogin($request);

        if (! $pending) {
            return to_route('admin.login')->with('error', 'Your sign-in timed out. Enter your password again.');
        }

        $user = $pending['user'];

        if ($lockout->isLocked($user)) {
            $request->session()->forget(TwoFactor::PENDING_LOGIN);

            return to_route('admin.login')->with('error', $lockout->message($user));
        }
        $useRecovery = $request->boolean('recovery');
        $field = $useRecovery ? 'recovery_code' : 'code';

        $request->validate([$field => ['required', 'string', 'max:40']], [
            'code.required' => 'Enter the 6-digit code from your authenticator app.',
            'recovery_code.required' => 'Enter one of your recovery codes.',
        ]);

        $key = 'two-factor-challenge:'.$user->id;

        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            $request->session()->forget(TwoFactor::PENDING_LOGIN);

            return to_route('admin.login')->with('error', 'Too many wrong codes. Wait a few minutes, then sign in again.');
        }

        $valid = $useRecovery
            ? $this->twoFactor->useRecoveryCode($user, (string) $request->input('recovery_code'))
            : $this->twoFactor->checkCode($user->two_factor_secret, (string) $request->input('code'), $user->id);

        if (! $valid) {
            RateLimiter::hit($key, 300);
            $this->twoFactor->log($user, 'two_factor_failed', $useRecovery ? 'Wrong recovery code at sign-in' : 'Wrong two-factor code at sign-in', $request, suspicious: true);

            if ($lockout->recordFailure($user, $request, $useRecovery ? 'recovery code' : 'two-factor code')) {
                $request->session()->forget(TwoFactor::PENDING_LOGIN);

                return to_route('admin.login')->with('error', $lockout->message($user->fresh()));
            }

            throw ValidationException::withMessages([
                $field => $useRecovery
                    ? 'That recovery code is wrong or was already used.'
                    : 'That code is wrong or has expired. Codes change every 30 seconds; use the one showing now.',
            ]);
        }

        RateLimiter::clear($key);
        $lockout->recordSuccess($user);
        $request->session()->forget(TwoFactor::PENDING_LOGIN);

        $login->complete($request, $user, (bool) $pending['remember'], $useRecovery ? 'recovery code' : 'two-factor code');

        if ($useRecovery) {
            $left = $this->twoFactor->remainingCodes($user);
            $this->twoFactor->log($user, 'two_factor_recovery', "Signed in with a recovery code; {$left} left", $request);
            $this->twoFactor->notify($user, 'recovery', $request);
        }

        if ($request->boolean('trust')) {
            Cookie::queue($this->twoFactor->trustCookie($user));
        }

        $redirect = redirect()->intended(route('admin.dashboard'));

        return $useRecovery
            ? $redirect->with('warning', 'You signed in with a recovery code. '.$this->twoFactor->remainingCodes($user).' left. If you lost your phone, set up two-factor again from your account.')
            : $redirect;
    }
}
