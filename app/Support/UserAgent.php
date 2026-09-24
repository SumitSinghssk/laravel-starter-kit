<?php

namespace App\Support;

class UserAgent
{
    public static function describe(?string $agent): array
    {
        $agent = (string) $agent;

        $browser = match (true) {
            $agent === '' => 'Unknown browser',
            str_contains($agent, 'Edg/') || str_contains($agent, 'EdgA/') || str_contains($agent, 'EdgiOS/') => 'Edge',
            str_contains($agent, 'OPR/') || str_contains($agent, 'Opera') => 'Opera',
            str_contains($agent, 'SamsungBrowser/') => 'Samsung Internet',
            str_contains($agent, 'Firefox/') || str_contains($agent, 'FxiOS/') => 'Firefox',
            str_contains($agent, 'CriOS/') || (str_contains($agent, 'Chrome/') && ! str_contains($agent, 'Chromium/')) => 'Chrome',
            str_contains($agent, 'Chromium/') => 'Chromium',
            str_contains($agent, 'Safari/') && str_contains($agent, 'Version/') => 'Safari',
            (bool) preg_match('/curl|wget|python|guzzle|postman|insomnia|symfony/i', $agent) => 'API client',
            default => 'Unknown browser',
        };

        $os = match (true) {
            str_contains($agent, 'iPhone') => 'iPhone',
            str_contains($agent, 'iPad') => 'iPad',
            str_contains($agent, 'Android') => 'Android',
            str_contains($agent, 'Windows') => 'Windows',
            str_contains($agent, 'CrOS') => 'ChromeOS',
            str_contains($agent, 'Mac OS X') || str_contains($agent, 'Macintosh') => 'macOS',
            str_contains($agent, 'Linux') => 'Linux',
            default => 'Unknown system',
        };

        $device = match (true) {
            str_contains($agent, 'iPad') || (str_contains($agent, 'Android') && ! str_contains($agent, 'Mobile')) || str_contains($agent, 'Tablet') => 'tablet',
            str_contains($agent, 'Mobile') || str_contains($agent, 'iPhone') => 'mobile',
            default => 'desktop',
        };

        return ['browser' => $browser, 'os' => $os, 'device' => $device];
    }
}
