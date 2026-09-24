<?php

namespace App\Support;

use App\Helpers\Settings;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\IpUtils;

class Maintenance
{
    public const KEY = 'maintenance';

    public const DEFAULTS = [
        'enabled' => false,
        'title' => "We'll be back soon",
        'message' => "We're making some improvements to the website. Please check back shortly.",
        'ends_at' => null,
        'allowed_ips' => [],
    ];

    public static function settings(): array
    {
        $stored = Settings::get(self::KEY);

        return [...self::DEFAULTS, ...(is_array($stored) ? $stored : [])];
    }

    public static function isOn(): bool
    {
        return (bool) self::settings()['enabled'];
    }

    public static function save(array $values): void
    {
        Setting::updateOrCreate(['key' => self::KEY], ['value' => [...self::settings(), ...$values]]);
        Settings::flush();
    }

    public static function allows(Request $request): bool
    {
        if (Auth::guard('web')->check()) {
            return true;
        }

        $ips = self::settings()['allowed_ips'];

        return $ips && IpUtils::checkIp((string) $request->ip(), $ips);
    }

    public static function expectedBack(): ?Carbon
    {
        $endsAt = self::settings()['ends_at'];

        if (! $endsAt) {
            return null;
        }

        $time = rescue(fn () => Carbon::parse($endsAt), null, report: false);

        return $time?->isFuture() ? $time : null;
    }

    public static function retryAfter(): int
    {
        $back = self::expectedBack();

        return $back ? max(60, (int) now()->diffInSeconds($back)) : 3600;
    }
}
