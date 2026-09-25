<?php

namespace App\Http\Middleware;

use App\Services\Firewall;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class BlockIps
{
    public function __construct(private Firewall $firewall) {}

    public function handle(Request $request, Closure $next): Response
    {
        $block = rescue(fn () => $this->firewall->blockFor($request->ip()), null, false);

        if (! $block) {
            return $next($request);
        }

        $block->forceFill(['hits' => $block->hits + 1, 'last_hit_at' => now()])->saveQuietly();
        $this->firewall->record($request, 'ip_blocked');

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Access from your network has been blocked.'], 403);
        }

        return response()->view('errors.blocked', ['until' => $block->expires_at], 403);
    }
}
