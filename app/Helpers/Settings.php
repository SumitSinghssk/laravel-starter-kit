<?php

namespace App\Helpers;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

class Settings
{
    const CACHE_KEY = 'app_settings';

    const CACHE_TTL = 60 * 60 * 24;

    protected static function all(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function () {
            return Setting::all()
                ->mapWithKeys(fn ($row) => [$row->key => $row->value])
                ->toArray();
        });
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return data_get(self::all(), $key, $default);
    }

    public static function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    public static function refresh(): void
    {
        self::flush();
        self::all();
    }

    public static function appName(): string
    {
        return self::get('basic_settings.app_name', config('app.name'));
    }

    public static function addresses(): array
    {
        return (array) self::get('basic_settings.addresses', []);
    }

    public static function phones(): array
    {
        return (array) self::get('basic_settings.phones', []);
    }

    public static function emails(): array
    {
        return (array) self::get('basic_settings.emails', []);
    }

    public static function socialLinks(): array
    {
        return (array) self::get('basic_settings.social_links', []);
    }

    public static function logoLight(): ?string
    {
        $path = self::get('basic_settings.logo.light');

        return media_url($path);
    }

    public static function logoDark(): ?string
    {
        $path = self::get('basic_settings.logo.dark');

        return media_url($path);
    }

    public static function favicon(): ?string
    {
        $path = self::get('basic_settings.favicon');

        return media_url($path);
    }
}
