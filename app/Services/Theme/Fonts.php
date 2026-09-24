<?php

namespace App\Services\Theme;

use App\Helpers\Settings;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class Fonts
{
    public const WEIGHTS = [100, 200, 300, 400, 500, 600, 700, 800, 900];

    public const FAMILY_PATTERN = '/^[A-Za-z0-9][A-Za-z0-9 \-]{0,59}$/';

    public const MAX_FILE_KB = 5120;

    private const FORMATS = ['woff2' => 'woff2', 'woff' => 'woff', 'ttf' => 'truetype', 'otf' => 'opentype'];

    private const GOOGLE_CSS = 'https://fonts.googleapis.com/css2';

    private const GOOGLE_FILES = 'https://fonts.gstatic.com/';

    private const USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0 Safari/537.36';

    public function all(): array
    {
        $fonts = Settings::get(Theme::KEY.'.fonts');

        return is_array($fonts) ? array_values($fonts) : [];
    }

    public function find(string $id): ?array
    {
        return collect($this->all())->firstWhere('id', $id);
    }

    public function familyTaken(string $family, ?string $exceptId = null): bool
    {
        return collect($this->all())->contains(fn ($font) => strcasecmp($font['family'], $family) === 0 && $font['id'] !== $exceptId);
    }

    public function stack(string $id): string
    {
        if ($id === 'serif-system') {
            return Theme::FALLBACKS['serif'][1];
        }

        $font = $id === 'system' ? null : $this->find($id);

        if (! $font) {
            return Theme::FALLBACKS['sans-serif'][1];
        }

        return "'{$font['family']}', ".(Theme::FALLBACKS[$font['fallback']][1] ?? Theme::FALLBACKS['sans-serif'][1]);
    }

    public function weights(string $id): array
    {
        $font = in_array($id, ['system', 'serif-system'], true) ? null : $this->find($id);

        if (! $font) {
            return self::WEIGHTS;
        }

        $weights = $font['source'] === 'google' ? $font['weights'] : array_column($font['files'], 'weight');
        $weights = array_values(array_unique(array_map('intval', $weights)));
        sort($weights);

        return $weights;
    }

    public function faces(array $fonts): string
    {
        $css = '';
        $disk = Storage::disk('public');

        foreach ($fonts as $font) {
            if ($font['source'] === 'google' && empty($font['self_hosted'])) {
                continue;
            }

            foreach ($font['files'] as $file) {
                $css .= "@font-face {\n  font-family: '{$font['family']}';\n"
                    ."  src: url('".$disk->url($file['path'])."') format('{$file['format']}');\n"
                    ."  font-weight: {$file['weight']};\n  font-style: {$file['style']};\n  font-display: swap;\n"
                    .(empty($file['unicode_range']) ? '' : "  unicode-range: {$file['unicode_range']};\n")
                    ."}\n";
            }
        }

        return $css;
    }

    public function googleLinks(?array $ids = null): array
    {
        return collect($this->all())
            ->filter(fn ($font) => $font['source'] === 'google' && empty($font['self_hosted']) && ($ids === null || in_array($font['id'], $ids, true)))
            ->map(fn ($font) => $this->googleUrl($font['family'], $font['weights'], (bool) $font['italic']))
            ->values()
            ->all();
    }

    public function googleUrl(string $family, array $weights, bool $italic): string
    {
        sort($weights);
        $axes = $italic
            ? 'ital,wght@'.implode(';', [...array_map(fn ($w) => "0,$w", $weights), ...array_map(fn ($w) => "1,$w", $weights)])
            : 'wght@'.implode(';', $weights);

        return self::GOOGLE_CSS.'?family='.str_replace(' ', '+', $family).':'.$axes.'&display=swap';
    }

    public function fromUploads(string $family, string $fallback, array $files, ?array $existing = null): array
    {
        $font = $existing ?? [
            'id' => Str::lower(Str::random(10)),
            'family' => $family,
            'source' => 'upload',
            'fallback' => $fallback,
            'weights' => [],
            'italic' => false,
            'self_hosted' => true,
            'files' => [],
        ];

        $disk = Storage::disk('public');
        $directory = 'fonts/'.Str::slug($font['family']).'-'.$font['id'];

        $formats = array_map(fn ($file) => $this->detectFormat($file[0]), $files);

        foreach ($files as $i => [$upload, $weight, $style]) {
            $format = $formats[$i];
            $path = $upload->storeAs($directory, "{$weight}-{$style}-".Str::lower(Str::random(6)).'.'.$format, 'public');

            if (! $path) {
                throw new RuntimeException('The font file could not be saved. Check that the storage folder is writable.');
            }

            foreach ($font['files'] as $index => $file) {
                if ((int) $file['weight'] === (int) $weight && $file['style'] === $style) {
                    $disk->delete($file['path']);
                    unset($font['files'][$index]);
                }
            }

            $font['files'][] = ['weight' => (int) $weight, 'style' => $style, 'path' => $path, 'format' => self::FORMATS[$format]];
        }

        usort($font['files'], fn ($a, $b) => [$a['weight'], $a['style']] <=> [$b['weight'], $b['style']]);
        $font['files'] = array_values($font['files']);
        $font['weights'] = array_values(array_unique(array_column($font['files'], 'weight')));
        $font['italic'] = in_array('italic', array_column($font['files'], 'style'), true);

        return $font;
    }

    public function fromGoogle(string $family, array $weights, bool $italic, string $fallback, bool $selfHost): array
    {
        $weights = array_values(array_unique(array_map('intval', $weights)));
        sort($weights);

        $css = $this->fetchGoogleCss($family, $weights, $italic);

        $font = [
            'id' => Str::lower(Str::random(10)),
            'family' => $family,
            'source' => 'google',
            'fallback' => $fallback,
            'weights' => $weights,
            'italic' => $italic,
            'self_hosted' => false,
            'files' => [],
        ];

        return $selfHost ? $this->selfHost($font, $css) : $font;
    }

    public function selfHost(array $font, ?string $css = null): array
    {
        $css ??= $this->fetchGoogleCss($font['family'], $font['weights'], (bool) $font['italic']);
        $disk = Storage::disk('public');
        $directory = 'fonts/'.Str::slug($font['family']).'-'.$font['id'];
        $files = [];
        $total = 0;

        preg_match_all('/@font-face\s*\{([^}]*)\}/', $css, $blocks);

        try {
            foreach ($blocks[1] as $n => $block) {
                if (! preg_match("/url\\((['\"]?)(https:\\/\\/fonts\\.gstatic\\.com\\/[^'\")\\s]+)\\1\\)/", $block, $url)
                    || ! preg_match('/font-weight:\s*(\d+)/', $block, $weight)
                    || ! preg_match('/font-style:\s*(normal|italic)/', $block, $style)) {
                    continue;
                }

                $response = Http::timeout(20)->withHeaders(['User-Agent' => self::USER_AGENT])->get($url[2]);
                if (! $response->successful() || ! str_starts_with($url[2], self::GOOGLE_FILES)) {
                    throw new RuntimeException('A font file could not be downloaded from Google Fonts. Try again, or load the font from Google instead.');
                }

                $body = $response->body();
                $total += strlen($body);
                if ($total > 30 * 1024 * 1024) {
                    throw new RuntimeException('This font is too large to host here (over 30 MB). Pick fewer weights, or load it from Google.');
                }

                $extension = str_starts_with($body, 'wOF2') ? 'woff2' : (str_starts_with($body, 'wOFF') ? 'woff' : 'ttf');
                $path = "{$directory}/g{$n}-{$weight[1]}-{$style[1]}.{$extension}";
                $disk->put($path, $body);

                $range = preg_match('/unicode-range:\s*([^;]+);/', $block, $r) ? trim($r[1]) : null;
                $files[] = ['weight' => (int) $weight[1], 'style' => $style[1], 'path' => $path, 'format' => self::FORMATS[$extension], 'unicode_range' => $range];
            }
        } catch (ConnectionException) {
            $disk->deleteDirectory($directory);

            throw new RuntimeException("Couldn't reach Google Fonts from this server. Check its internet connection, or try again later.");
        } catch (RuntimeException $e) {
            $disk->deleteDirectory($directory);

            throw $e;
        }

        if (! $files) {
            throw new RuntimeException('Google Fonts sent no font files for this family.');
        }

        return [...$font, 'self_hosted' => true, 'files' => $files];
    }

    public function unhost(array $font): array
    {
        $this->deleteFiles($font);

        return [...$font, 'self_hosted' => false, 'files' => []];
    }

    public function deleteFiles(array $font): void
    {
        $disk = Storage::disk('public');

        foreach ($font['files'] as $file) {
            $disk->delete($file['path']);
        }

        $directory = 'fonts/'.Str::slug($font['family']).'-'.$font['id'];
        if ($disk->exists($directory) && ! $disk->allFiles($directory)) {
            $disk->deleteDirectory($directory);
        }
    }

    public function detectFormat(UploadedFile $file): string
    {
        $head = (string) @file_get_contents($file->getRealPath(), false, null, 0, 4);

        $format = match (true) {
            $head === 'wOF2' => 'woff2',
            $head === 'wOFF' => 'woff',
            $head === 'OTTO' => 'otf',
            $head === "\x00\x01\x00\x00", $head === 'true' => 'ttf',
            default => null,
        };

        if ($format === null) {
            throw new RuntimeException("“{$file->getClientOriginalName()}” is not a font file. Upload WOFF2, WOFF, TTF or OTF files.");
        }

        return $format;
    }

    private function fetchGoogleCss(string $family, array $weights, bool $italic): string
    {
        try {
            $response = Http::timeout(15)->withHeaders(['User-Agent' => self::USER_AGENT])->get($this->googleUrl($family, $weights, $italic));

            if ($response->status() === 400) {
                $familyOnly = Http::timeout(15)->withHeaders(['User-Agent' => self::USER_AGENT])->get(self::GOOGLE_CSS.'?family='.str_replace(' ', '+', $family));

                throw new RuntimeException($familyOnly->successful()
                    ? "“{$family}” doesn't come in all the weights".($italic ? ' or italics' : '').' you picked. Check the styles listed on fonts.google.com and pick those.'
                    : "Google Fonts has no font called “{$family}”. Check the spelling and capitals exactly as on fonts.google.com (e.g. “Playfair Display”).");
            }

            if (! $response->successful() || ! str_contains($response->body(), '@font-face')) {
                throw new RuntimeException('Google Fonts did not answer properly (HTTP '.$response->status().'). Try again in a moment.');
            }

            return $response->body();
        } catch (ConnectionException) {
            throw new RuntimeException("Couldn't reach Google Fonts from this server. Check its internet connection, or try again later.");
        }
    }
}
