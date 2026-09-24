<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Request;

class Authenticate extends Middleware
{
    protected function redirectTo(Request $request): ?string
    {
        if (! $request->expectsJson()) {
            $guards = array_keys(config('auth.guards'));

            foreach ($guards as $guard) {
                if ($request->is("$guard/*")) {
                    return route("$guard.login");
                }
            }

            $routeName = $request->route()?->getName();
            if ($routeName && str_starts_with($routeName, 'admin.')) {
                return route('admin.login');
            }

            return route('admin.login');
        }

        return null;
    }
}
