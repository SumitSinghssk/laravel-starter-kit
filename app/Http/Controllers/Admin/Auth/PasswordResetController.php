<?php

namespace App\Http\Controllers\Admin\Auth;

use App\Http\Controllers\Controller;
use App\Services\AdminPasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;

class PasswordResetController extends Controller
{
    public function __construct(private AdminPasswordReset $resets) {}

    public function create()
    {
        return view('admin.auth.forgot-password', [
            'canDeliver' => AdminPasswordReset::canDeliver(),
            'minutes' => (int) config('auth.passwords.users.expire', 60),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate(['email' => ['required', 'string', 'email:rfc', 'max:255']]);

        $this->resets->sendLink($validated['email'], $request->ip(), $request->userAgent());

        return back()->withInput()->with('reset_link_sent', $validated['email']);
    }

    public function edit(Request $request, string $token)
    {
        $email = (string) $request->query('email', '');

        return view('admin.auth.reset-password', [
            'token' => $token,
            'email' => $email,
            'valid' => $email !== '' && $this->resets->tokenIsValid($email, $token),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string', 'confirmed', PasswordRule::defaults()],
        ]);

        $status = $this->resets->reset($validated, $request->ip(), $request->userAgent());

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'password' => 'This reset link is invalid or has expired. Ask for a new one.',
            ]);
        }

        return to_route('admin.login')
            ->withInput(['email' => $validated['email']])
            ->with('success', 'Your password was changed and you were signed out everywhere. Sign in with the new password.');
    }
}
