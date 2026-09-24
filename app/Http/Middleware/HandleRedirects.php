<?php

namespace App\Http\Middleware;

use App\Models\Redirect;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class HandleRedirects
{
    private const EXCLUDED = ['admin', 'admin/*', 'up', 'build/*', 'storage/*'];

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->isMethod('GET') && ! $request->isMethod('HEAD')) {
            return $next($request);
        }

        if ($request->is(...self::EXCLUDED)) {
            return $next($request);
        }

        $map = rescue(fn () => Redirect::activeMap(), [], report: false);

        $match = $map[Redirect::normalizePath($request->getPathInfo())] ?? null;

        if (! $match) {
            return $next($request);
        }

        [$id, $target, $code] = $match;

        rescue(fn () => Redirect::whereKey($id)->toBase()->increment('hits', 1, ['last_hit_at' => now()]), report: false);

        return new RedirectResponse($this->targetUrl($target, $request), $code);
    }

    private function targetUrl(string $target, Request $request): string
    {
        $url = str_starts_with($target, '/') && ! str_starts_with($target, '//') ? url($target) : $target;

        $query = $request->getQueryString();

        if ($query && ! str_contains($url, '?')) {
            [$url, $fragment] = array_pad(explode('#', $url, 2), 2, null);
            $url .= '?'.$query.($fragment !== null ? '#'.$fragment : '');
        }

        return $url;
    }
}
