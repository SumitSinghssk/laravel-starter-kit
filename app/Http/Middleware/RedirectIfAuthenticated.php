<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RedirectIfAuthenticated
{
    public function handle(Request $request, Closure $next, ...$guards)
    {
        foreach ($guards as $guard) {
            if (Auth::guard($guard)->check()) {

                if ($request->is('admin/*')) {
                    return redirect()->route('admin.dashboard');
                }

                return redirect('/');
            }
        }

        return $next($request);
    }
}
