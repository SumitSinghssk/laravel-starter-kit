<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdminLogin
{
    public function complete(Request $request, User $user, bool $remember, ?string $via = null): void
    {
        if (! Auth::guard('web')->check()) {
            Auth::guard('web')->login($user, $remember);
        }

        $request->session()->regenerate();
        $request->session()->put('auth_signed_in_at', now()->timestamp);

        ActivityLogger::login($user, $request);

        notify(
            'Admin Login',
            'New Login',
            "{$user->name} logged into admin panel".($via ? " ({$via})" : ''),
            [
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ],
            route('admin.users.edit', $user)
        );
    }
}
