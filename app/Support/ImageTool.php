<?php

namespace App\Support;

use GdImage;
use Illuminate\Validation\ValidationException;

class ImageTool
{
    private const MAX_PIXELS = 50_000_000;

    public static function load(string $file, ?string $mime = null): GdImage
    {
        $info = @getimagesize($file);

        if (! $info) {
            throw ValidationException::withMessages(['file' => 'This file could not be read as an image.']);
        }

        if ($info[0] * $info[1] > self::MAX_PIXELS) {
            throw ValidationException::withMessages(['file' => "This image is {$info[0]} × {$info[1]} px, which is too large to process. Please resize it first."]);
        }

        $mime ??= $info['mime'];

        $image = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($file),
            'image/png' => @imagecreatefrompng($file),
            'image/webp' => @imagecreatefromwebp($file),
            'image/gif' => @imagecreatefromgif($file),
            default => false,
        };

        if (! $image) {
            throw ValidationException::withMessages(['file' => 'This image could not be processed. It may be damaged.']);
        }

        if (! imageistruecolor($image)) {
            imagepalettetotruecolor($image);
        }

        return $mime === 'image/jpeg' ? self::applyOrientation($image, $file) : $image;
    }

    public static function fit(GdImage $image, int $maxWidth, int $maxHeight): GdImage
    {
        $width = imagesx($image);
        $height = imagesy($image);
        $scale = min(1, $maxWidth / $width, $maxHeight / $height);

        if ($scale >= 1) {
            return $image;
        }

        return self::resample($image, 0, 0, $width, $height, max(1, (int) round($width * $scale)), max(1, (int) round($height * $scale)));
    }

    public static function cover(GdImage $image, int $width, int $height): GdImage
    {
        $sourceWidth = imagesx($image);
        $sourceHeight = imagesy($image);
        $scale = max($width / $sourceWidth, $height / $sourceHeight);

        $cropWidth = (int) round($width / $scale);
        $cropHeight = (int) round($height / $scale);

        return self::resample(
            $image,
            (int) floor(($sourceWidth - $cropWidth) / 2),
            (int) floor(($sourceHeight - $cropHeight) / 2),
            $cropWidth,
            $cropHeight,
            $width,
            $height,
        );
    }

    public static function encode(GdImage $image, string $format, int $quality = 85): string
    {
        ob_start();

        $ok = match (strtolower($format)) {
            'jpg', 'jpeg' => imagejpeg(self::flatten($image), null, $quality),
            'png' => self::png($image),
            'webp' => imagesavealpha($image, true) && imagewebp($image, null, $quality),
            'gif' => imagegif($image),
            default => false,
        };

        $bytes = ob_get_clean();

        if (! $ok || $bytes === '' || $bytes === false) {
            throw ValidationException::withMessages(['file' => "The image could not be saved as {$format}."]);
        }

        return $bytes;
    }

    private static function png(GdImage $image): bool
    {
        imagesavealpha($image, true);

        return imagepng($image, null, 6);
    }

    private static function flatten(GdImage $image): GdImage
    {
        $flat = imagecreatetruecolor(imagesx($image), imagesy($image));
        imagefill($flat, 0, 0, imagecolorallocate($flat, 255, 255, 255));
        imagecopy($flat, $image, 0, 0, 0, 0, imagesx($image), imagesy($image));

        return $flat;
    }

    private static function resample(GdImage $image, int $x, int $y, int $width, int $height, int $newWidth, int $newHeight): GdImage
    {
        $resized = imagecreatetruecolor($newWidth, $newHeight);
        imagealphablending($resized, false);
        imagesavealpha($resized, true);
        imagefill($resized, 0, 0, imagecolorallocatealpha($resized, 0, 0, 0, 127));
        imagecopyresampled($resized, $image, 0, 0, $x, $y, $newWidth, $newHeight, $width, $height);

        return $resized;
    }

    private static function applyOrientation(GdImage $image, string $file): GdImage
    {
        if (! function_exists('exif_read_data')) {
            return $image;
        }

        $angle = match ((int) (@exif_read_data($file)['Orientation'] ?? 1)) {
            3 => 180,
            6 => -90,
            8 => 90,
            default => 0,
        };

        return $angle ? (imagerotate($image, $angle, 0) ?: $image) : $image;
    }
}
