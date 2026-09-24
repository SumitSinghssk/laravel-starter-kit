<?php

namespace App\Http\Middleware;

use App\Models\NotFoundLog;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RecordNotFound
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($response->getStatusCode() === 404
            && config('not_found.enabled')
            && ($request->isMethod('GET') || $request->isMethod('HEAD'))
            && ! $request->is(...config('not_found.ignore'))) {
            rescue(fn () => NotFoundLog::record($request), report: false);
        }

        return $response;
    }
}
