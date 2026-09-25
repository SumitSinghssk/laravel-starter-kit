<?php

namespace App\Http\Middleware;

use App\Services\Firewall;
use App\Support\BotCheck;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class ProtectForm
{
    public function __construct(private Firewall $firewall) {}

    public function handle(Request $request, Closure $next, string $field = 'email', string $kind = 'form'): Response
    {
        $failure = BotCheck::verify($request, $kind === 'login');

        if ($failure) {
            $this->firewall->record($request, $failure);

            throw ValidationException::withMessages([
                $field => $failure === 'captcha_failed'
                    ? 'Please complete the “I am human” check and try again.'
                    : 'We couldn’t confirm you’re a person. Please wait a moment and try again.',
            ]);
        }

        return $next($request);
    }
}
