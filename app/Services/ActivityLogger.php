<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Request as RequestFacade;

class ActivityLogger
{
    public static function log(
        string $action,
        ?Model $model = null,
        array $oldValues = [],
        array $newValues = [],
        ?string $description = null
    ): ActivityLog {
        return ActivityLog::create([
            'user_id' => Auth::id(),
            'action' => $action,
            'model_type' => $model ? get_class($model) : null,
            'model_id' => $model?->getKey(),
            'model_name' => $model ? self::getModelName($model) : null,
            'old_values' => $oldValues ?: null,
            'new_values' => $newValues ?: null,
            'ip_address' => RequestFacade::ip(),
            'user_agent' => RequestFacade::userAgent(),
            'url' => RequestFacade::fullUrl(),
            'method' => RequestFacade::method(),
            'description' => $description,
        ]);
    }

    public static function created(Model $model, ?string $description = null): ActivityLog
    {
        return self::log('created', $model, [], self::safeAttributes($model), $description);
    }

    public static function updated(Model $model, array $oldValues, array $newValues, ?string $description = null): ActivityLog
    {
        return self::log('updated', $model, $oldValues, $newValues, $description);
    }

    public static function deleted(Model $model, ?string $description = null): ActivityLog
    {
        return self::log('deleted', $model, self::safeAttributes($model), [], $description);
    }

    protected static function safeAttributes(Model $model): array
    {
        $exclude = method_exists($model, 'trackExcluded')
            ? $model->trackExcluded()
            : ['password', 'remember_token'];

        return array_diff_key($model->getAttributes(), array_flip($exclude));
    }

    public static function synced(
        Model $model,
        string $relation,
        array $oldIds,
        array $newIds,
        ?string $description = null
    ): ?ActivityLog {
        $added = array_diff($newIds, $oldIds);
        $removed = array_diff($oldIds, $newIds);

        if (empty($added) && empty($removed)) {
            return null;
        }

        return self::log(
            'updated',
            $model,
            [$relation => $oldIds],
            [$relation => $newIds],
            $description ?? ucfirst($relation).' updated: '
                .(! empty($added) ? '+'.count($added).' added ' : '')
                .(! empty($removed) ? '-'.count($removed).' removed' : ''),
        );
    }

    public static function login(User $user, Request $request): ActivityLog
    {
        $location = self::getLocation($request->ip());
        $device = self::parseUserAgent($request->userAgent());

        return ActivityLog::create([
            'user_id' => $user->id,
            'action' => 'login',
            'email' => $user->email,
            'ip_address' => $request->ip(),
            'url' => $request->fullUrl(),
            'method' => $request->method(),
            'login_at' => now(),
            'session_id' => $request->session()->getId(),
            'is_suspicious' => self::isSuspicious($user, $request->ip()),
            'description' => 'User logged in',
            ...$location,
            ...$device,
        ]);
    }

    public static function logout(User $user, Request $request): void
    {
        $log = ActivityLog::where('user_id', $user->id)
            ->where('session_id', $request->session()->getId())
            ->where('action', 'login')
            ->whereNull('logout_at')
            ->latest()
            ->first();

        if ($log) {
            $log->update([
                'logout_at' => now(),
                'session_duration' => $log->login_at
                    ? abs((int) now()->diffInSeconds($log->login_at))
                    : null,
                'description' => 'User logged out',
            ]);

            ActivityLog::create([
                'user_id' => $user->id,
                'action' => 'logout',
                'email' => $user->email,
                'ip_address' => $request->ip(),
                'url' => $request->fullUrl(),
                'method' => $request->method(),
                'session_id' => $request->session()->getId(),
                'description' => 'User logged out',
                'user_agent' => $log->user_agent,
                'browser' => $log->browser,
                'browser_version' => $log->browser_version,
                'platform' => $log->platform,
                'device_type' => $log->device_type,
                'device' => $log->device,
                'country' => $log->country,
                'country_code' => $log->country_code,
                'region' => $log->region,
                'city' => $log->city,
                'latitude' => $log->latitude,
                'longitude' => $log->longitude,
                'timezone' => $log->timezone,
                'isp' => $log->isp,
            ]);
        }
    }

    public static function failedLogin(string $email, Request $request): ActivityLog
    {
        $location = self::getLocation($request->ip());
        $device = self::parseUserAgent($request->userAgent());
        $user = User::where('email', $email)->first();

        return ActivityLog::create([
            'user_id' => $user?->id,
            'action' => 'failed_login',
            'email' => $email,
            'ip_address' => $request->ip(),
            'url' => $request->fullUrl(),
            'method' => $request->method(),
            'login_at' => now(),
            'session_id' => $request->session()->getId(),
            'is_suspicious' => true,
            'description' => "Failed login attempt for: {$email}",
            ...$location,
            ...$device,
        ]);
    }

    private static function getLocation(string $ip): array
    {
        $blank = [
            'country' => null, 'country_code' => null, 'region' => null,
            'city' => null, 'latitude' => null, 'longitude' => null,
            'timezone' => null, 'isp' => null,
        ];

        if (self::isLocalIp($ip)) {
            return array_merge($blank, ['city' => 'Localhost', 'country' => 'Local']);
        }

        try {
            $r = Http::timeout(3)->get("http://ip-api.com/json/{$ip}?fields=status,country,countryCode,regionName,city,lat,lon,timezone,isp");
            if ($r->ok() && $r->json('status') === 'success') {
                $d = $r->json();

                return [
                    'country' => $d['country'] ?? null,
                    'country_code' => $d['countryCode'] ?? null,
                    'region' => $d['regionName'] ?? null,
                    'city' => $d['city'] ?? null,
                    'latitude' => $d['lat'] ?? null,
                    'longitude' => $d['lon'] ?? null,
                    'timezone' => $d['timezone'] ?? null,
                    'isp' => $d['isp'] ?? null,
                ];
            }
        } catch (\Exception $e) {
            Log::warning("ActivityLogger ip-api.com failed [{$ip}]: ".$e->getMessage());
        }

        try {
            $r = Http::timeout(3)->get("https://ipapi.co/{$ip}/json/");
            if ($r->ok() && empty($r->json('error'))) {
                $d = $r->json();

                return [
                    'country' => $d['country_name'] ?? null,
                    'country_code' => $d['country_code'] ?? null,
                    'region' => $d['region'] ?? null,
                    'city' => $d['city'] ?? null,
                    'latitude' => $d['latitude'] ?? null,
                    'longitude' => $d['longitude'] ?? null,
                    'timezone' => $d['timezone'] ?? null,
                    'isp' => $d['org'] ?? null,
                ];
            }
        } catch (\Exception $e) {
            Log::warning("ActivityLogger ipapi.co failed [{$ip}]: ".$e->getMessage());
        }

        try {
            $r = Http::timeout(3)->get("https://freeipapi.com/api/json/{$ip}");
            if ($r->ok()) {
                $d = $r->json();

                return [
                    'country' => $d['countryName'] ?? null,
                    'country_code' => $d['countryCode'] ?? null,
                    'region' => $d['regionName'] ?? null,
                    'city' => $d['cityName'] ?? null,
                    'latitude' => $d['latitude'] ?? null,
                    'longitude' => $d['longitude'] ?? null,
                    'timezone' => $d['timeZone'] ?? null,
                    'isp' => null,
                ];
            }
        } catch (\Exception $e) {
            Log::warning("ActivityLogger freeipapi.com failed [{$ip}]: ".$e->getMessage());
        }

        return $blank;
    }

    private static function isLocalIp(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
    }

    private static function parseUserAgent(?string $ua): array
    {
        if (! $ua) {
            return [
                'user_agent' => null, 'browser' => 'Unknown',
                'browser_version' => null, 'platform' => 'Unknown',
                'device_type' => 'desktop', 'device' => null,
            ];
        }

        return [
            'user_agent' => $ua,
            'browser' => self::detectBrowser($ua),
            'browser_version' => self::detectBrowserVersion($ua),
            'platform' => self::detectPlatform($ua),
            'device_type' => self::detectDeviceType($ua),
            'device' => self::detectDevice($ua),
        ];
    }

    private static function detectBrowser(string $ua): string
    {
        return match (true) {
            str_contains($ua, 'Edg/') => 'Edge',
            str_contains($ua, 'OPR/') => 'Opera',
            str_contains($ua, 'SamsungBrowser') => 'Samsung Browser',
            str_contains($ua, 'UCBrowser') => 'UC Browser',
            str_contains($ua, 'YaBrowser') => 'Yandex',
            str_contains($ua, 'Chrome/') => 'Chrome',
            str_contains($ua, 'Firefox/') => 'Firefox',
            str_contains($ua, 'Safari/') && str_contains($ua, 'Version/') => 'Safari',
            str_contains($ua, 'MSIE') || str_contains($ua, 'Trident/') => 'Internet Explorer',
            default => 'Unknown',
        };
    }

    private static function detectBrowserVersion(string $ua): ?string
    {
        $patterns = [
            '/Edg\/([\d.]+)/',
            '/OPR\/([\d.]+)/',
            '/SamsungBrowser\/([\d.]+)/',
            '/UCBrowser\/([\d.]+)/',
            '/YaBrowser\/([\d.]+)/',
            '/Chrome\/([\d.]+)/',
            '/Firefox\/([\d.]+)/',
            '/Version\/([\d.]+)/',
            '/MSIE ([\d.]+)/',
            '/rv:([\d.]+)\) Gecko/',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $ua, $m)) {
                $parts = explode('.', $m[1]);

                return $parts[0].(isset($parts[1]) ? '.'.$parts[1] : '');
            }
        }

        return null;
    }

    private static function detectPlatform(string $ua): string
    {
        return match (true) {
            str_contains($ua, 'Windows NT 10.0') => 'Windows 10/11',
            str_contains($ua, 'Windows NT 6.3') => 'Windows 8.1',
            str_contains($ua, 'Windows NT 6.1') => 'Windows 7',
            str_contains($ua, 'Windows') => 'Windows',
            str_contains($ua, 'Mac OS X') => 'macOS',
            str_contains($ua, 'Android') => 'Android',
            str_contains($ua, 'iPhone') => 'iOS',
            str_contains($ua, 'iPad') => 'iPadOS',
            str_contains($ua, 'CrOS') => 'ChromeOS',
            str_contains($ua, 'Linux') => 'Linux',
            default => 'Unknown',
        };
    }

    private static function detectDeviceType(string $ua): string
    {
        if (str_contains($ua, 'iPad') ||
            (str_contains($ua, 'Android') && ! str_contains($ua, 'Mobile'))) {
            return 'tablet';
        }
        if (str_contains($ua, 'Mobile') || str_contains($ua, 'iPhone') ||
            str_contains($ua, 'Android')) {
            return 'mobile';
        }

        return 'desktop';
    }

    private static function detectDevice(string $ua): ?string
    {
        if (str_contains($ua, 'iPhone')) {
            return 'iPhone';
        }
        if (str_contains($ua, 'iPad')) {
            return 'iPad';
        }

        if (preg_match('/Android[\d. ;]+;\s*([^)]+)\)/', $ua, $m)) {
            $model = trim($m[1]);
            if (! in_array(strtolower($model), ['mobile', 'tablet', 'android', ''])) {
                return $model;
            }
        }

        return null;
    }

    private static function getModelName(Model $model): string
    {
        $className = class_basename($model);
        foreach (['name', 'title', 'email', 'username'] as $field) {
            if (! empty($model->{$field})) {
                return "{$className}: {$model->{$field}}";
            }
        }

        return "{$className} #{$model->getKey()}";
    }

    public static function viewed(string $pageTitle, ?Model $model = null, ?string $description = null): ActivityLog
    {
        return ActivityLog::create([
            'user_id' => Auth::id(),
            'action' => 'viewed',
            'page_title' => $pageTitle,
            'model_type' => $model ? get_class($model) : null,
            'model_id' => $model?->getKey(),
            'model_name' => $model ? self::getModelName($model) : null,
            'ip_address' => RequestFacade::ip(),
            'user_agent' => RequestFacade::userAgent(),
            'url' => RequestFacade::fullUrl(),
            'method' => RequestFacade::method(),
            'description' => $description,
        ]);
    }

    private static function isSuspicious(User $user, string $ip): bool
    {
        return ! ActivityLog::where('user_id', $user->id)
            ->where('ip_address', $ip)
            ->where('action', 'login')
            ->exists();
    }
}
