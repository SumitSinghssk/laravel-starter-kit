<?php

namespace App\Http\Middleware;

use App\Services\ActivityLogger;
use App\Support\SecuritySettings;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class IdleTimeout
{
    public const KEY = 'last_activity_at';

    public function handle(Request $request, Closure $next): Response
    {
        $minutes = (int) SecuritySettings::get('idle_minutes');
        $user = $request->user();

        if (! $user || $minutes <= 0) {
            return $next($request);
        }

        $last = (int) $request->session()->get(self::KEY, now()->timestamp);

        if (now()->timestamp - $last >= $minutes * 60) {
            $intended = $request->isMethod('GET') && ! $request->expectsJson() ? $request->fullUrl() : url()->previous();

            rescue(fn () => ActivityLogger::logout($user, $request), report: false);
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            $request->session()->put('url.intended', $intended);

            $message = "You were signed out after {$minutes} ".Str::plural('minute', $minutes).' without activity. Sign in again to carry on where you left off.';

            if ($request->expectsJson()) {
                return response()->json(['message' => $message, 'signed_out' => true], 401);
            }

            return to_route('admin.login')->with('error', $message);
        }

        $request->session()->put(self::KEY, now()->timestamp);

        return $next($request);
    }
}
