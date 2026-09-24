<?php

namespace App\Services\Media;

use App\Models\MediaFile;
use App\Support\ImageTool;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class MediaLibrary
{
    public function roots(): array
    {
        return array_map(fn ($root) => rtrim(str_replace('\\', '/', $root), '/'), config('media.roots'));
    }

    public function root(string $location): string
    {
        return $this->roots()[$location] ?? throw new RuntimeException("Unknown media location [{$location}].");
    }

    public function absolutePath(string $location, string $path): string
    {
        $path = str_replace('\\', '/', $path);

        if ($path === '' || str_starts_with($path, '/') || preg_match('#(^|/)\.\.(/|$)#', $path) || str_contains($path, ':')) {
            throw new RuntimeException('Invalid media path.');
        }

        $root = $this->root($location);
        $absolute = $root.'/'.$path;
        $real = realpath($absolute);
        $realRoot = realpath($root);

        if ($real !== false && $realRoot !== false && ! str_starts_with(str_replace('\\', '/', $real), str_replace('\\', '/', $realRoot).'/')) {
            throw new RuntimeException('Media path is outside its folder.');
        }

        return $absolute;
    }

    public function url(string $location, string $path): string
    {
        $encoded = implode('/', array_map('rawurlencode', explode('/', $path)));

        return $location === MediaFile::STORAGE ? asset('storage/'.$encoded) : asset($encoded);
    }

    public function isExcluded(string $location, string $path): bool
    {
        foreach (config("media.exclude.{$location}", []) as $pattern) {
            if (Str::is($pattern, $path) || Str::is($pattern.'/*', $path)) {
                return true;
            }
        }

        return false;
    }

    public function locate(string $reference): ?array
    {
        $reference = html_entity_decode(trim($reference));
        $path = parse_url($reference, PHP_URL_PATH);
        $host = parse_url($reference, PHP_URL_HOST);

        if (! is_string($path) || $path === '' || ($host && strcasecmp($host, (string) parse_url(url('/'), PHP_URL_HOST)) !== 0)) {
            return null;
        }

        $path = rawurldecode($path);

        $base = rtrim((string) parse_url(url('/'), PHP_URL_PATH), '/');
        if ($base !== '' && str_starts_with($path, $base.'/')) {
            $path = substr($path, strlen($base));
        }

        $path = ltrim($path, '/');

        if ($path === '') {
            return null;
        }

        return str_starts_with($path, 'storage/')
            ? [MediaFile::STORAGE, substr($path, 8)]
            : [MediaFile::PUBLIC, $path];
    }

    public function describe(string $location, string $path): array
    {
        $absolute = $this->absolutePath($location, $path);
        clearstatcache(true, $absolute);

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        [$width, $height] = $extension === 'svg' ? $this->svgSize($absolute) : $this->rasterSize($absolute);
        $folder = str_contains($path, '/') ? substr($path, 0, strrpos($path, '/')) : '';

        return [
            'location' => $location,
            'path' => $path,
            'folder' => $folder,
            'filename' => basename($path),
            'extension' => $extension === 'jpeg' ? 'jpg' : $extension,
            'size' => (int) filesize($absolute),
            'width' => $width,
            'height' => $height,
            'modified_at' => Carbon::createFromTimestamp(filemtime($absolute)),
        ];
    }

    public function thumbnail(MediaFile $file): string
    {
        $disk = Storage::disk('local');
        $path = "media-thumbs/{$file->id}-{$file->version}.webp";

        if (! $disk->exists($path)) {
            $size = config('media.thumb_size');
            $image = ImageTool::fit(ImageTool::load($file->absolutePath()), $size, $size);
            $disk->put($path, ImageTool::encode($image, 'webp', 80));
        }

        return $disk->path($path);
    }

    public function forgetThumbnails(MediaFile $file): void
    {
        $disk = Storage::disk('local');

        foreach ($disk->files('media-thumbs') as $thumb) {
            if (str_starts_with(basename($thumb), $file->id.'-')) {
                $disk->delete($thumb);
            }
        }
    }

    private function rasterSize(string $file): array
    {
        $info = @getimagesize($file);

        return $info ? [$info[0] ?: null, $info[1] ?: null] : [null, null];
    }

    private function svgSize(string $file): array
    {
        $head = (string) @file_get_contents($file, false, null, 0, 4096);

        if (! preg_match('/<svg\b[^>]*>/is', $head, $m)) {
            return [null, null];
        }

        $number = fn (string $attribute) => preg_match('/\s'.$attribute.'\s*=\s*["\']\s*([\d.]+)(px)?\s*["\']/i', $m[0], $v) ? (int) round((float) $v[1]) : null;
        $width = $number('width');
        $height = $number('height');

        if ((! $width || ! $height) && preg_match('/viewBox\s*=\s*["\']\s*[-\d.]+[\s,]+[-\d.]+[\s,]+([\d.]+)[\s,]+([\d.]+)/i', $m[0], $box)) {
            [$width, $height] = [(int) round((float) $box[1]), (int) round((float) $box[2])];
        }

        return [$width ?: null, $height ?: null];
    }
}
