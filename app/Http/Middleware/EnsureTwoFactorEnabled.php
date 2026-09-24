<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTwoFactorEnabled
{
    private const ALLOWED = ['admin.two-factor.*', 'admin.logout'];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || $request->routeIs(...self::ALLOWED) || $user->hasTwoFactor() || ! $user->requiresTwoFactor()) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Set up two-factor sign-in to continue.'], 403);
        }

        return to_route('admin.two-factor.show')->with('warning', 'Your role requires two-factor sign-in. Set it up to continue.');
    }
}
