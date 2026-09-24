<?php

use App\Helpers\Settings;
use App\Models\Notification;
use App\Services\Media\MediaLibrary;

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
