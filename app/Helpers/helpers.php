<?php

use App\Helpers\Settings;
use App\Models\Notification;
use App\Services\Media\MediaLibrary;
use App\Support\LocalTime;

if (! function_exists('settings')) {
    function settings(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return Settings::get('', []);
        }

        return Settings::get($key, $default);
    }
}

if (! function_exists('media_url')) {
    function media_url(?string $path, string $location = 'storage'): ?string
    {
        if (blank($path)) {
            return null;
        }

        static $versions = [];
        $path = ltrim(str_replace('\\', '/', $path), '/');
        $library = app(MediaLibrary::class);
        $key = "{$location}:{$path}";

        $versions[$key] ??= rescue(fn () => @filemtime($library->absolutePath($location, $path)) ?: null, null, report: false);

        return $library->url($location, $path).($versions[$key] ? '?v='.$versions[$key] : '');
    }
}

if (! function_exists('notify')) {
    function notify(
        string $type,
        string $title,
        ?string $message = null,
        array $data = [],
        ?string $url = null
    ): Notification {
        return Notification::create([
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'data' => $data,
            'url' => $url,
        ]);
    }
}

if (! function_exists('local_date')) {
    function local_date(mixed $value): ?string
    {
        return LocalTime::date($value);
    }
}

if (! function_exists('local_time')) {
    function local_time(mixed $value): ?string
    {
        return LocalTime::time($value);
    }
}

if (! function_exists('local_datetime')) {
    function local_datetime(mixed $value): ?string
    {
        return LocalTime::dateTime($value);
    }
}

if (! function_exists('local_day')) {
    function local_day(mixed $value): ?string
    {
        return LocalTime::day($value);
    }
}
