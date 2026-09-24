<?php

namespace App\Http\Controllers\Admin\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\TwoFactor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Gate;

class TwoFactorController extends Controller
{
    public function __construct(private TwoFactor $twoFactor) {}

    public function show(Request $request)
    {
        $user = $request->user();
        $secret = $this->twoFactor->pendingSecret($request);

        return view('admin.auth.two-factor', [
            'user' => $user,
            'enabled' => $user->hasTwoFactor(),
            'required' => $user->requiresTwoFactor(),
            'setup' => $secret ? [
                'qr' => $this->twoFactor->qrSvg($user, $secret),
                'key' => TwoFactor::formatSecret($secret),
                'url' => $this->twoFactor->otpauthUrl($user, $secret),
            ] : null,
            'newCodes' => $request->session()->get(TwoFactor::NEW_CODES),
            'codesLeft' => $this->twoFactor->remainingCodes($user),
            'trusted' => $user->hasTwoFactor() && $this->twoFactor->trusts($request, $user),
        ]);
    }

    public function start(Request $request)
    {
        $user = $request->user();

        if ($user->hasTwoFactor()) {
            $request->validateWithBag('twoFactor', ['password' => ['required', 'current_password:web']], [
                'password.required' => 'Enter your password to set up a new app.',
            ]);
        }

        $this->twoFactor->startSetup($request);

        return to_route('admin.two-factor.show')->withFragment('setup');
    }

    public function confirm(Request $request)
    {
        $request->validateWithBag('twoFactor', ['code' => ['required', 'string', 'max:20']], [
            'code.required' => 'Enter the 6-digit code your app shows.',
        ]);

        if (! $this->twoFactor->pendingSecret($request)) {
            return to_route('admin.two-factor.show')->with('error', 'The setup timed out. Start again.');
        }

        $codes = $this->twoFactor->confirm($request, $request->user(), (string) $request->input('code'));

        if ($codes === null) {
            return back()->withErrors(['code' => "That code doesn't match. Check the time on your phone is set automatically, and use the code showing now."], 'twoFactor')->withFragment('setup');
        }

        return to_route('admin.two-factor.show')
            ->with(TwoFactor::NEW_CODES, $codes)
            ->with('success', 'Two-factor sign-in is on. Save your recovery codes now.')
            ->withFragment('codes');
    }

    public function cancel(Request $request)
    {
        $this->twoFactor->cancelSetup($request);

        return to_route('admin.two-factor.show');
    }

    public function recoveryCodes(Request $request)
    {
        $user = $request->user();
        abort_unless($user->hasTwoFactor(), 404);

        $request->validateWithBag('twoFactor', ['password' => ['required', 'current_password:web']], [
            'password.required' => 'Enter your password to make new codes.',
        ]);

        return to_route('admin.two-factor.show')
            ->with(TwoFactor::NEW_CODES, $this->twoFactor->regenerateRecoveryCodes($request, $user))
            ->with('success', 'New recovery codes made. The old ones no longer work.')
            ->withFragment('codes');
    }

    public function destroy(Request $request)
    {
        $user = $request->user();
        abort_unless($user->hasTwoFactor(), 404);

        if ($user->requiresTwoFactor()) {
            return back()->with('error', 'Your role requires two-factor sign-in, so it can\'t be turned off. Set it up with a new app instead.');
        }

        $request->validateWithBag('twoFactor', ['password' => ['required', 'current_password:web']], [
            'password.required' => 'Enter your password to turn it off.',
        ]);

        $this->twoFactor->disable($request, $user);
        Cookie::queue(Cookie::forget(TwoFactor::TRUST_COOKIE));

        return to_route('admin.two-factor.show')->with('success', 'Two-factor sign-in is off.');
    }

    public function forgetDevice()
    {
        Cookie::queue(Cookie::forget(TwoFactor::TRUST_COOKIE));

        return to_route('admin.two-factor.show')->with('success', 'This browser will ask for a code at the next sign-in.');
    }

    public function reset(Request $request, User $user)
    {
        Gate::authorize('admin.users.two-factor');
        abort_if($user->is($request->user()), 403, 'Use your own two-factor page to change your settings.');

        if (! $user->hasTwoFactor()) {
            return back()->with('error', "{$user->name} doesn't use two-factor sign-in.");
        }

        $this->twoFactor->disable($request, $user, $request->user());

        return back()->with('success', "Two-factor sign-in was reset for {$user->name}.".($user->requiresTwoFactor() ? ' They will be asked to set it up again when they next sign in.' : ''));
    }
}
