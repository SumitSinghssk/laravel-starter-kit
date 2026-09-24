<?php

namespace App\Http\Middleware;

use Closure;
use Symfony\Component\HttpFoundation\Response;

class SecureHeaders
{
    public function handle($request, Closure $next)
    {
        $response = $next($request);

        if ($response instanceof Response) {

            if ($response->headers->get('X-Frame-Options') !== 'SAMEORIGIN') {
                $response->headers->set('X-Frame-Options', 'DENY');
            }

            $response->headers->set('X-Content-Type-Options', 'nosniff');

            $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

            $response->headers->set('X-XSS-Protection', '1; mode=block');

            if ($request->isSecure()) {
                $response->headers->set(
                    'Strict-Transport-Security',
                    'max-age=31536000; includeSubDomains; preload'
                );
            }
        }

        return $response;
    }
}
