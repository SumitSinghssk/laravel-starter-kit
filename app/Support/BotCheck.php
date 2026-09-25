<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Throwable;

class BotCheck
{
    public const HONEYPOT = 'website';

    public const STARTED = '_started';

    public const VERIFY_URLS = [
        'turnstile' => 'https://challenges.cloudflare.com/turnstile/v0/siteverify',
        'hcaptcha' => 'https://api.hcaptcha.com/siteverify',
    ];

    public const SCRIPTS = [
        'turnstile' => 'https://challenges.cloudflare.com/turnstile/v0/api.js',
        'hcaptcha' => 'https://js.hcaptcha.com/1/api.js',
    ];

    public const RESPONSE_FIELDS = [
        'turnstile' => 'cf-turnstile-response',
        'hcaptcha' => 'h-captcha-response',
    ];

    public static function startedToken(): string
    {
        return Crypt::encryptString((string) now()->getTimestampMs());
    }

    public static function captchaFor(bool $isLogin): ?string
    {
        if (! SecuritySettings::captchaEnabled()) {
            return null;
        }

        if ($isLogin && ! SecuritySettings::get('captcha_on_login')) {
            return null;
        }

        return SecuritySettings::get('captcha');
    }

    public static function verify(Request $request, bool $isLogin = false): ?string
    {
        $settings = SecuritySettings::all();

        if ($settings['honeypot']) {
            if (filled($request->input(self::HONEYPOT))) {
                return 'honeypot';
            }

            $started = rescue(fn () => (int) Crypt::decryptString((string) $request->input(self::STARTED)), null, false);
            $minimum = max(0, (float) $settings['min_submit_seconds']) * 1000;

            if (! $started || (now()->getTimestampMs() - $started) < $minimum) {
                return 'too_fast';
            }
        }

        $provider = self::captchaFor($isLogin);

        if ($provider && ! self::captchaPasses($provider, (string) $request->input(self::RESPONSE_FIELDS[$provider]), $request->ip())) {
            return 'captcha_failed';
        }

        return null;
    }

    public static function captchaPasses(string $provider, string $token, ?string $ip): bool
    {
        if ($token === '') {
            return false;
        }

        try {
            $response = Http::asForm()->timeout(8)->post(self::VERIFY_URLS[$provider], [
                'secret' => SecuritySettings::captchaSecret(),
                'response' => $token,
                'remoteip' => $ip,
            ]);

            return (bool) $response->json('success');
        } catch (Throwable $e) {
            report($e);

            return true;
        }
    }
}
