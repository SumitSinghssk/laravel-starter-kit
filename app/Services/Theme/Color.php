<?php

namespace App\Services\Theme;

class Color
{
    public const SHADES = [50, 100, 200, 300, 400, 500, 600, 700, 800, 900, 950];

    private const SCALE = [
        50 => [0.971, 0.09], 100 => [0.936, 0.14], 200 => [0.885, 0.25], 300 => [0.808, 0.43],
        400 => [0.704, 0.65], 500 => [0.637, 0.88], 600 => [0.577, 1.0], 700 => [0.505, 0.95],
        800 => [0.444, 0.78], 900 => [0.396, 0.58], 950 => [0.258, 0.36],
    ];

    private const KEYWORDS = [
        'white' => [1, 1, 1, 1],
        'black' => [0, 0, 0, 1],
        'transparent' => [0, 0, 0, 0],
    ];

    public static function parse(?string $value): ?array
    {
        $value = strtolower(trim((string) $value));

        if ($value === '' || strlen($value) > 80) {
            return null;
        }

        if (isset(self::KEYWORDS[$value])) {
            return self::KEYWORDS[$value];
        }

        if (preg_match('/^#([0-9a-f]{3,4}|[0-9a-f]{6}|[0-9a-f]{8})$/', $value, $m)) {
            $hex = strlen($m[1]) <= 4 ? implode('', array_map(fn ($c) => $c.$c, str_split($m[1]))) : $m[1];
            $parts = array_map(fn ($pair) => hexdec($pair) / 255, str_split($hex, 2));

            return [$parts[0], $parts[1], $parts[2], $parts[3] ?? 1.0];
        }

        if (! preg_match('/^(rgba?|hsla?|oklch)\(\s*([^()]*)\)$/', $value, $m)) {
            return null;
        }

        $args = self::arguments($m[2]);
        if ($args === null) {
            return null;
        }
        [$channels, $alpha] = $args;

        return match ($m[1]) {
            'rgb', 'rgba' => self::fromRgbArgs($channels, $alpha),
            'hsl', 'hsla' => self::fromHslArgs($channels, $alpha),
            'oklch' => self::fromOklchArgs($channels, $alpha),
        };
    }

    public static function isValid(?string $value): bool
    {
        return self::parse($value) !== null;
    }

    public static function shades(string $value): ?array
    {
        $rgb = self::parse($value);
        if ($rgb === null) {
            return null;
        }

        [, $chroma, $hue] = self::toOklch($rgb);
        $shades = [];

        foreach (self::SCALE as $shade => [$lightness, $relative]) {
            $shades[$shade] = self::oklchString($lightness, min($chroma * $relative, 0.37), $hue, $rgb[3]);
        }

        return $shades;
    }

    public static function contrast(array $foreground, array $background): float
    {
        $background = self::over($background, [1, 1, 1, 1]);
        $foreground = self::over($foreground, $background);
        [$a, $b] = [self::luminance($foreground), self::luminance($background)];

        return (max($a, $b) + 0.05) / (min($a, $b) + 0.05);
    }

    public static function toHex(array $rgb): string
    {
        return sprintf('#%02x%02x%02x', ...array_map(fn ($c) => (int) round(max(0, min(1, $c)) * 255), array_slice($rgb, 0, 3)));
    }

    public static function toOklch(array $rgb): array
    {
        [$r, $g, $b] = array_map(fn ($c) => $c <= 0.04045 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4, array_slice($rgb, 0, 3));

        $l = 0.4122214708 * $r + 0.5363325363 * $g + 0.0514459929 * $b;
        $m = 0.2119034982 * $r + 0.6806995451 * $g + 0.1073969566 * $b;
        $s = 0.0883024619 * $r + 0.2817188376 * $g + 0.6299787005 * $b;
        [$l, $m, $s] = [self::cbrt($l), self::cbrt($m), self::cbrt($s)];

        $L = 0.2104542553 * $l + 0.7936177850 * $m - 0.0040720468 * $s;
        $A = 1.9779984951 * $l - 2.4285922050 * $m + 0.4505937099 * $s;
        $B = 0.0259040371 * $l + 0.7827717662 * $m - 0.8086757660 * $s;

        $hue = rad2deg(atan2($B, $A));

        return [$L, sqrt($A * $A + $B * $B), $hue < 0 ? $hue + 360 : $hue];
    }

    public static function fromOklch(float $L, float $C, float $H, float $alpha = 1.0): array
    {
        $A = $C * cos(deg2rad($H));
        $B = $C * sin(deg2rad($H));

        $l = ($L + 0.3963377774 * $A + 0.2158037573 * $B) ** 3;
        $m = ($L - 0.1055613458 * $A - 0.0638541728 * $B) ** 3;
        $s = ($L - 0.0894841775 * $A - 1.2914855480 * $B) ** 3;

        $linear = [
            4.0767416621 * $l - 3.3077115913 * $m + 0.2309699292 * $s,
            -1.2684380046 * $l + 2.6097574011 * $m - 0.3413193965 * $s,
            -0.0041960863 * $l - 0.7034186147 * $m + 1.7076147010 * $s,
        ];

        $srgb = array_map(function ($c) {
            $c = max(0, min(1, $c));

            return $c <= 0.0031308 ? 12.92 * $c : 1.055 * $c ** (1 / 2.4) - 0.055;
        }, $linear);

        return [...$srgb, $alpha];
    }

    private static function oklchString(float $L, float $C, float $H, float $alpha): string
    {
        $text = sprintf('oklch(%s%% %s %s', self::number($L * 100, 1), self::number($C, 3), self::number($H, 1));

        return $text.($alpha < 1 ? ' / '.self::number($alpha, 2) : '').')';
    }

    private static function number(float $value, int $decimals): string
    {
        $text = rtrim(rtrim(number_format($value, $decimals, '.', ''), '0'), '.');

        return $text === '-0' ? '0' : $text;
    }

    private static function arguments(string $inner): ?array
    {
        $inner = trim($inner);
        $alpha = 1.0;
        $slash = str_contains($inner, '/');

        if ($slash) {
            [$inner, $alphaText] = array_map('trim', explode('/', $inner, 2));
            $alpha = self::alpha($alphaText);
            if ($alpha === null) {
                return null;
            }
        }

        $parts = str_contains($inner, ',') ? array_map('trim', explode(',', $inner)) : preg_split('/\s+/', $inner);

        if (count($parts) === 4 && ! $slash) {
            $alpha = self::alpha(array_pop($parts));
            if ($alpha === null) {
                return null;
            }
        }

        if (count($parts) !== 3) {
            return null;
        }

        foreach ($parts as $part) {
            if (! preg_match('/^-?(\d+\.?\d*|\.\d+)(%|deg)?$/', $part)) {
                return null;
            }
        }

        return [$parts, $alpha];
    }

    private static function alpha(string $text): ?float
    {
        if (! preg_match('/^(\d+\.?\d*|\.\d+)(%)?$/', $text, $m)) {
            return null;
        }
        $value = isset($m[2]) ? (float) $m[1] / 100 : (float) $m[1];

        return $value <= 1 ? $value : null;
    }

    private static function fromRgbArgs(array $parts, float $alpha): ?array
    {
        $channels = [];
        foreach ($parts as $part) {
            if (str_ends_with($part, 'deg')) {
                return null;
            }
            $value = str_ends_with($part, '%') ? (float) $part / 100 : (float) $part / 255;
            if ($value < 0 || $value > 1) {
                return null;
            }
            $channels[] = $value;
        }

        return [...$channels, $alpha];
    }

    private static function fromHslArgs(array $parts, float $alpha): ?array
    {
        $hue = fmod((float) $parts[0], 360);
        $hue = $hue < 0 ? $hue + 360 : $hue;
        $saturation = (float) $parts[1] / 100;
        $light = (float) $parts[2] / 100;

        if ($saturation < 0 || $saturation > 1 || $light < 0 || $light > 1) {
            return null;
        }

        $f = function ($n) use ($hue, $saturation, $light) {
            $k = fmod($n + $hue / 30, 12);

            return $light - $saturation * min($light, 1 - $light) * max(-1, min($k - 3, 9 - $k, 1));
        };

        return [$f(0), $f(8), $f(4), $alpha];
    }

    private static function fromOklchArgs(array $parts, float $alpha): ?array
    {
        $L = str_ends_with($parts[0], '%') ? (float) $parts[0] / 100 : (float) $parts[0];
        $C = str_ends_with($parts[1], '%') ? (float) $parts[1] / 100 * 0.4 : (float) $parts[1];
        $H = (float) $parts[2];

        if ($L < 0 || $L > 1 || $C < 0 || $C > 0.5) {
            return null;
        }

        return self::fromOklch($L, $C, $H, $alpha);
    }

    private static function over(array $top, array $bottom): array
    {
        $a = $top[3];

        return [
            $top[0] * $a + $bottom[0] * (1 - $a),
            $top[1] * $a + $bottom[1] * (1 - $a),
            $top[2] * $a + $bottom[2] * (1 - $a),
            1,
        ];
    }

    private static function luminance(array $rgb): float
    {
        [$r, $g, $b] = array_map(fn ($c) => $c <= 0.04045 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4, array_slice($rgb, 0, 3));

        return 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;
    }

    private static function cbrt(float $x): float
    {
        return $x < 0 ? -((-$x) ** (1 / 3)) : $x ** (1 / 3);
    }
}
