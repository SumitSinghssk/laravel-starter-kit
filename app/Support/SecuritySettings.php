<?php

namespace App\Support;

use App\Helpers\Settings;
use App\Models\Setting;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;

class SecuritySettings
{
    public const KEY = 'security_settings';

    public const CAPTCHAS = [
        'none' => 'Off',
        'turnstile' => 'Cloudflare Turnstile',
        'hcaptcha' => 'hCaptcha',
    ];

    public const DEFAULTS = [
        'lockout_enabled' => true,
        'lockout_attempts' => 5,
        'lockout_minutes' => 15,
        'idle_minutes' => 60,
        'login_per_minute' => 5,
        'password_reset_per_minute' => 5,
        'two_factor_per_minute' => 10,
        'forms_per_minute' => 5,
        'honeypot' => true,
        'min_submit_seconds' => 2,
        'captcha' => 'none',
        'captcha_site_key' => '',
        'captcha_secret' => null,
        'captcha_on_login' => false,
        'auto_block' => true,
        'auto_block_after' => 30,
        'auto_block_window' => 10,
        'auto_block_minutes' => 60,
        'log_days' => 30,
    ];

    public static function all(): array
    {
        $stored = Settings::get(self::KEY);

        return [...self::DEFAULTS, ...(is_array($stored) ? $stored : [])];
    }

    public static function get(string $key): mixed
    {
        return self::all()[$key] ?? self::DEFAULTS[$key] ?? null;
    }

    public static function forForm(): array
    {
        $settings = self::all();
        $settings['has_captcha_secret'] = filled($settings['captcha_secret']);
        unset($settings['captcha_secret']);

        return $settings;
    }

    public static function captchaSecret(): ?string
    {
        $secret = self::all()['captcha_secret'];

        if (blank($secret)) {
            return null;
        }

        try {
            return Crypt::decryptString($secret);
        } catch (DecryptException) {
            return null;
        }
    }

    public static function captchaEnabled(): bool
    {
        $settings = self::all();

        return $settings['captcha'] !== 'none' && filled($settings['captcha_site_key']) && filled(self::captchaSecret());
    }

    public static function save(array $values, ?string $newSecret, bool $removeSecret): void
    {
        $current = self::all();

        $secret = match (true) {
            $removeSecret => null,
            filled($newSecret) => Crypt::encryptString($newSecret),
            default => $current['captcha_secret'],
        };

        Setting::updateOrCreate(['key' => self::KEY], ['value' => [...$current, ...$values, 'captcha_secret' => $secret]]);
        Settings::flush();
    }
}
