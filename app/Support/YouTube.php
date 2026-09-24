<?php

namespace App\Support;

class YouTube
{
    private const HOSTS = ['youtube.com', 'youtu.be', 'youtube-nocookie.com'];

    public static function videoId(?string $input): ?string
    {
        $input = trim((string) $input);

        if (preg_match('/<iframe\b[^>]*\bsrc\s*=\s*(["\'])(.*?)\1/is', $input, $m)) {
            $input = html_entity_decode($m[2]);
        }

        if (! preg_match('#^https?://#i', $input)) {
            $input = 'https://'.ltrim($input, '/');
        }

        $parts = parse_url($input);
        $host = strtolower($parts['host'] ?? '');
        $host = preg_replace('/^(www\.|m\.|music\.)/', '', $host);

        if (! in_array($host, self::HOSTS, true)) {
            return null;
        }

        $path = $parts['path'] ?? '';
        parse_str($parts['query'] ?? '', $query);

        $candidate = match (true) {
            $host === 'youtu.be' => explode('/', trim($path, '/'))[0] ?? null,
            isset($query['v']) && is_string($query['v']) => $query['v'],
            (bool) preg_match('#^/(embed|shorts|live|v)/([^/?]+)#', $path, $m) => $m[2],
            default => null,
        };

        return is_string($candidate) && preg_match('/^[A-Za-z0-9_-]{11}$/', $candidate) ? $candidate : null;
    }
}
