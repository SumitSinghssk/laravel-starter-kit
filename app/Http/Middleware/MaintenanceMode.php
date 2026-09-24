<?php

namespace App\Http\Middleware;

use App\Support\Maintenance;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class MaintenanceMode
{
    private const EXCEPT = ['admin', 'admin/*', 'up'];

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->is(...self::EXCEPT) || ! rescue(fn () => Maintenance::isOn(), false, report: false) || Maintenance::allows($request)) {
            return $next($request);
        }

        return response()->view('maintenance', ['maintenance' => Maintenance::settings(), 'back' => Maintenance::expectedBack()], 503, [
            'Retry-After' => Maintenance::retryAfter(),
            'Cache-Control' => 'no-store',
        ]);
    }
}
