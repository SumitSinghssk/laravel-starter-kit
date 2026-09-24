<?php

namespace App\Services\Transfer;

use App\Services\Media\MediaLibrary;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class RemoteImage
{
    public const MAX_BYTES = 10 * 1024 * 1024;

    private const TYPES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];

    private array $saved = [];

    public function __construct(private MediaLibrary $library) {}

    public function commit(): void
    {
        $this->saved = [];
    }

    public function rollback(): void
    {
        Storage::disk('public')->delete($this->saved);
        $this->saved = [];
    }

    public function store(string $reference, string $directory): string
    {
        $reference = trim($reference);

        if ($local = $this->localFile($reference)) {
            return $this->save(file_get_contents($local), $directory);
        }

        if (! preg_match('#^https?://#i', $reference)) {
            throw new RuntimeException("Image “{$reference}” is not a web address (http:// or https://) or a file in storage.");
        }

        return $this->save($this->download($reference), $directory);
    }

    private function localFile(string $reference): ?string
    {
        $located = preg_match('#^https?://#i', $reference)
            ? $this->library->locate($reference)
            : ['storage', ltrim(preg_replace('#^/?storage/#', '', $reference), '/')];

        if (! $located || $located[0] !== 'storage' || $located[1] === '') {
            return null;
        }

        $path = rescue(fn () => $this->library->absolutePath('storage', $located[1]), null, report: false);

        return $path && is_file($path) ? $path : null;
    }

    private function download(string $url): string
    {
        for ($hop = 0; $hop <= 3; $hop++) {
            [$host, $ip, $port] = $this->resolvePublic($url);

            $response = Http::timeout(20)
                ->withOptions([
                    'allow_redirects' => false,
                    'curl' => [CURLOPT_RESOLVE => ["{$host}:{$port}:{$ip}"]],
                    'stream' => true,
                ])
                ->withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; '.config('app.name').' importer)', 'Accept' => 'image/*'])
                ->get($url);

            if ($response->redirect()) {
                $location = $response->header('Location');
                $url = str_starts_with($location, 'http') ? $location : (string) UriResolver::resolve(new Uri($url), new Uri($location));

                continue;
            }

            if (! $response->successful()) {
                throw new RuntimeException("The image could not be downloaded (HTTP {$response->status()}).");
            }

            $body = $response->toPsrResponse()->getBody();
            $bytes = '';

            while (! $body->eof()) {
                $bytes .= $body->read(65536);

                if (strlen($bytes) > self::MAX_BYTES) {
                    throw new RuntimeException('The image is larger than 10 MB.');
                }
            }

            return $bytes;
        }

        throw new RuntimeException('The image address redirects too many times.');
    }

    private function resolvePublic(string $url): array
    {
        $parts = parse_url($url);
        $host = strtolower(trim($parts['host'] ?? '', '[]'));
        $scheme = strtolower($parts['scheme'] ?? '');

        if ($host === '' || ! in_array($scheme, ['http', 'https'], true)) {
            throw new RuntimeException('The image address is not valid.');
        }

        $ips = filter_var($host, FILTER_VALIDATE_IP)
            ? [$host]
            : array_merge(gethostbynamel($host) ?: [], array_column(@dns_get_record($host, DNS_AAAA) ?: [], 'ipv6'));

        if (! $ips) {
            throw new RuntimeException("The image host “{$host}” could not be found.");
        }

        foreach ($ips as $ip) {
            if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                throw new RuntimeException("Images can't be downloaded from private or local addresses ({$host}).");
            }
        }

        return [$host, $ips[0], $parts['port'] ?? ($scheme === 'https' ? 443 : 80)];
    }

    private function save(string $bytes, string $directory): string
    {
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes) ?: '';

        if (! isset(self::TYPES[$mime]) || ! @getimagesizefromstring($bytes)) {
            throw new RuntimeException('The file at that address is not a JPG, PNG, WebP or GIF image.');
        }

        $path = trim($directory, '/').'/'.Str::random(32).'.'.self::TYPES[$mime];
        Storage::disk('public')->put($path, $bytes);
        $this->saved[] = $path;

        return $path;
    }
}
