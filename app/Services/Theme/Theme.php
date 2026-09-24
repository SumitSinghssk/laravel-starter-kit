<?php

namespace App\Services\Theme;

use App\Helpers\Settings;
use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Theme
{
    public const KEY = 'appearance';

    public const MAX_VARIABLES = 80;

    public const NAME_PATTERN = '/^[a-z][a-z0-9]*(-[a-z0-9]+)*$/';

    public const RADII = [
        'none' => ['Square', '0px'],
        'sm' => ['Subtle', '0.25rem'],
        'md' => ['Soft', '0.5rem'],
        'lg' => ['Rounded', '0.75rem'],
        'xl' => ['Extra rounded', '1rem'],
        'full' => ['Pill', '9999px'],
    ];

    public const FALLBACKS = [
        'sans-serif' => ['Sans-serif', "ui-sans-serif, system-ui, -apple-system, 'Segoe UI', Roboto, Arial, sans-serif"],
        'serif' => ['Serif', "ui-serif, Georgia, Cambria, 'Times New Roman', serif"],
        'monospace' => ['Monospace', 'ui-monospace, SFMono-Regular, Menlo, Consolas, monospace'],
        'cursive' => ['Handwriting', 'cursive'],
    ];

    public const SLOTS = [
        'page' => ['Page', 'Page background', 'Behind everything', 'white', 'ink', null],
        'surface' => ['Page', 'Cards & panels', 'Boxes that sit on the page', 'neutral-50', 'neutral-900', null],
        'surface-alt' => ['Page', 'Alternate sections', 'Every other section, highlighted areas', 'neutral-100', 'neutral-800', null],
        'text' => ['Page', 'Body text', 'Paragraphs and lists', 'neutral-700', 'neutral-300', 'page'],
        'heading' => ['Page', 'Headings', 'h1 – h6', 'ink', 'white', 'page'],
        'muted' => ['Page', 'Muted text', 'Dates, captions, hints', 'neutral-500', 'neutral-400', 'page'],
        'border' => ['Page', 'Borders & dividers', 'Lines between things', 'neutral-200', 'neutral-800', null],
        'link' => ['Page', 'Links', 'Links inside text', 'primary-600', 'primary-400', 'page'],
        'link-hover' => ['Page', 'Links on hover', '', 'primary-700', 'primary-300', 'page'],

        'header' => ['Header', 'Header background', 'The top bar', 'white', 'ink', null],
        'header-text' => ['Header', 'Header text', 'Menu links', 'neutral-700', 'neutral-300', 'header'],
        'header-active' => ['Header', 'Current menu item', 'The page you are on, and hover', 'primary-600', 'primary-400', 'header'],

        'button' => ['Buttons', 'Main button', 'Background of the main call-to-action', 'primary-600', 'primary-500', null],
        'button-text' => ['Buttons', 'Main button text', '', 'white', 'white', 'button'],
        'button-hover' => ['Buttons', 'Main button on hover', '', 'primary-700', 'primary-400', null],
        'button-secondary' => ['Buttons', 'Second button', 'Background of the less important button', 'neutral-100', 'neutral-800', null],
        'button-secondary-text' => ['Buttons', 'Second button text', '', 'ink', 'white', 'button-secondary'],
        'button-secondary-hover' => ['Buttons', 'Second button on hover', '', 'neutral-200', 'neutral-700', null],

        'accent' => ['Highlights', 'Accent', 'Badges, stars, small highlights', 'accent-400', 'accent-400', null],
        'accent-text' => ['Highlights', 'Text on accent', '', 'ink', 'ink', 'accent'],

        'input' => ['Forms', 'Field background', 'Inputs, selects, text areas', 'white', 'neutral-900', null],
        'input-text' => ['Forms', 'Field text', '', 'ink', 'white', 'input'],
        'input-border' => ['Forms', 'Field border', '', 'neutral-300', 'neutral-700', null],
        'focus' => ['Forms', 'Focus ring', 'Outline of the field being typed in', 'primary-500', 'primary-400', null],

        'footer' => ['Footer', 'Footer background', '', 'ink', 'black', null],
        'footer-text' => ['Footer', 'Footer text', '', 'neutral-300', 'neutral-400', 'footer'],
        'footer-heading' => ['Footer', 'Footer headings', '', 'white', 'white', 'footer'],
        'footer-link' => ['Footer', 'Footer links', '', 'neutral-100', 'neutral-200', 'footer'],

        'success' => ['Messages', 'Success', 'Sent, saved, in stock', 'success', 'success', null],
        'warning' => ['Messages', 'Warning', 'Almost sold out, check this', 'warning', 'warning', null],
        'danger' => ['Messages', 'Error', 'Problems, required fields', 'danger', 'danger', null],
        'info' => ['Messages', 'Information', 'Tips and notices', 'info', 'info', null],
    ];

    public function __construct(private Fonts $fonts) {}

    public function defaults(): array
    {
        return [
            'variables' => [
                ['name' => 'primary', 'value' => '#4f46e5', 'shades' => true],
                ['name' => 'accent', 'value' => '#f59e0b', 'shades' => true],
                ['name' => 'neutral', 'value' => '#64748b', 'shades' => true],
                ['name' => 'ink', 'value' => '#0f172a', 'shades' => false],
                ['name' => 'white', 'value' => '#ffffff', 'shades' => false],
                ['name' => 'black', 'value' => '#020617', 'shades' => false],
                ['name' => 'success', 'value' => '#16a34a', 'shades' => false],
                ['name' => 'warning', 'value' => '#d97706', 'shades' => false],
                ['name' => 'danger', 'value' => '#dc2626', 'shades' => false],
                ['name' => 'info', 'value' => '#0284c7', 'shades' => false],
            ],
            'slots' => array_map(fn ($slot) => ['light' => $slot[3], 'dark' => null], self::SLOTS),
            'typography' => ['heading_font' => 'system', 'body_font' => 'system', 'heading_weight' => 700, 'base_size' => 16],
            'style' => ['radius' => 'md', 'dark_mode' => 'off'],
        ];
    }

    public function get(): array
    {
        $saved = Settings::get(self::KEY);
        $saved = is_array($saved) ? $saved : [];
        $defaults = $this->defaults();

        $theme = [
            'variables' => isset($saved['variables']) && is_array($saved['variables']) ? array_values($saved['variables']) : $defaults['variables'],
            'slots' => $defaults['slots'],
            'typography' => array_merge($defaults['typography'], (array) ($saved['typography'] ?? [])),
            'style' => array_merge($defaults['style'], (array) ($saved['style'] ?? [])),
            'fonts' => array_values((array) ($saved['fonts'] ?? [])),
            'version' => (int) ($saved['version'] ?? 0),
        ];

        foreach ((array) ($saved['slots'] ?? []) as $key => $slot) {
            if (isset($theme['slots'][$key]) && is_array($slot)) {
                $theme['slots'][$key] = ['light' => $slot['light'] ?? $theme['slots'][$key]['light'], 'dark' => $slot['dark'] ?? null];
            }
        }

        $palette = $this->palette($theme['variables']);
        foreach ($theme['slots'] as $key => $slot) {
            if (! isset($palette[$slot['light']])) {
                $theme['slots'][$key]['light'] = isset($palette[self::SLOTS[$key][3]]) ? self::SLOTS[$key][3] : array_key_first($palette);
            }
            if ($slot['dark'] !== null && ! isset($palette[$slot['dark']])) {
                $theme['slots'][$key]['dark'] = null;
            }
        }

        return $theme;
    }

    public function palette(array $variables): array
    {
        $palette = ['transparent' => 'transparent'];

        foreach ($variables as $variable) {
            $name = (string) ($variable['name'] ?? '');
            $value = trim((string) ($variable['value'] ?? ''));

            if (! preg_match(self::NAME_PATTERN, $name) || ! Color::isValid($value)) {
                continue;
            }

            $palette[$name] = $value;

            if (! empty($variable['shades'])) {
                foreach (Color::shades($value) as $shade => $css) {
                    $palette["{$name}-{$shade}"] ??= $css;
                }
            }
        }

        return $palette;
    }

    public function validate(array $input): array
    {
        $errors = [];
        $variables = [];
        $names = [];

        $rows = array_values(array_filter((array) ($input['variables'] ?? []), 'is_array'));

        if (count($rows) > self::MAX_VARIABLES) {
            $errors['variables'] = 'Use at most '.self::MAX_VARIABLES.' colour variables.';
        }

        foreach ($rows as $i => $row) {
            $name = strtolower(trim((string) ($row['name'] ?? '')));
            $value = trim((string) ($row['value'] ?? ''));

            if ($name === '') {
                $errors["variables.$i.name"] = 'Give the colour a name.';
            } elseif (strlen($name) > 40) {
                $errors["variables.$i.name"] = 'Keep the name under 40 characters.';
            } elseif (! preg_match(self::NAME_PATTERN, $name)) {
                $errors["variables.$i.name"] = 'Use lowercase letters, numbers and single dashes, starting with a letter (e.g. footer-bg).';
            } elseif (isset($names[$name])) {
                $errors["variables.$i.name"] = "“{$name}” is used twice. Each colour needs its own name.";
            } elseif ($name === 'transparent') {
                $errors["variables.$i.name"] = '“transparent” is built in. Choose another name.';
            }

            if (! Color::isValid($value)) {
                $errors["variables.$i.value"] = $value === ''
                    ? 'Pick a colour.'
                    : 'Not a colour this site understands. Use #hex, rgb(), hsl() or oklch().';
            }

            $names[$name] = $i;
            $variables[] = ['name' => $name, 'value' => $value, 'shades' => filter_var($row['shades'] ?? false, FILTER_VALIDATE_BOOLEAN)];
        }

        foreach ($variables as $i => $variable) {
            if (preg_match('/^(.+)-(\d+)$/', $variable['name'], $m) && in_array((int) $m[2], Color::SHADES, true)) {
                $parent = $names[$m[1]] ?? null;
                if ($parent !== null && $variables[$parent]['shades']) {
                    $errors["variables.$i.name"] ??= "“{$m[1]}” already makes a shade called “{$variable['name']}”. Rename this colour or turn off shades for “{$m[1]}”.";
                }
            }
        }

        if (! $variables) {
            $errors['variables'] = 'Keep at least one colour.';
        }

        $palette = $this->palette($variables);
        $slots = [];

        foreach (self::SLOTS as $key => $slot) {
            $given = (array) (($input['slots'] ?? [])[$key] ?? []);
            $light = (string) ($given['light'] ?? $slot[3]);
            $dark = filled($given['dark'] ?? null) ? (string) $given['dark'] : null;

            if (! isset($palette[$light])) {
                $errors["slots.$key.light"] = "Pick a colour for “{$slot[1]}”: “{$light}” doesn't exist any more.";
            }
            if ($dark !== null && ! isset($palette[$dark])) {
                $errors["slots.$key.dark"] = "Pick a dark-mode colour for “{$slot[1]}”: “{$dark}” doesn't exist any more.";
            }

            $slots[$key] = ['light' => $light, 'dark' => $dark];
        }

        $typography = (array) ($input['typography'] ?? []);
        $fontIds = array_merge(['system', 'serif-system'], array_column($this->fonts->all(), 'id'));
        $clean = [
            'heading_font' => (string) ($typography['heading_font'] ?? 'system'),
            'body_font' => (string) ($typography['body_font'] ?? 'system'),
            'heading_weight' => (int) ($typography['heading_weight'] ?? 700),
            'base_size' => (int) ($typography['base_size'] ?? 16),
        ];

        foreach (['heading_font' => 'headings', 'body_font' => 'body text'] as $field => $label) {
            if (! in_array($clean[$field], $fontIds, true)) {
                $errors["typography.$field"] = "Choose a font for {$label}: that font was removed.";
            }
        }
        if (! in_array($clean['heading_weight'], [100, 200, 300, 400, 500, 600, 700, 800, 900], true)) {
            $errors['typography.heading_weight'] = 'Choose a weight between 100 and 900.';
        }
        if ($clean['base_size'] < 14 || $clean['base_size'] > 20) {
            $errors['typography.base_size'] = 'Choose a text size between 14 and 20 px.';
        }

        $style = (array) ($input['style'] ?? []);
        $cleanStyle = [
            'radius' => (string) ($style['radius'] ?? 'md'),
            'dark_mode' => (string) ($style['dark_mode'] ?? 'off'),
        ];
        if (! isset(self::RADII[$cleanStyle['radius']])) {
            $errors['style.radius'] = 'Choose a corner style.';
        }
        if (! in_array($cleanStyle['dark_mode'], ['off', 'auto'], true)) {
            $errors['style.dark_mode'] = 'Choose whether the site has a dark mode.';
        }

        return [['variables' => $variables, 'slots' => $slots, 'typography' => $clean, 'style' => $cleanStyle], $errors];
    }

    public function save(array $clean): void
    {
        $current = Settings::get(self::KEY);
        $current = is_array($current) ? $current : [];

        $this->store([...$current, ...$clean]);
    }

    public function saveFonts(array $fonts): void
    {
        $current = Settings::get(self::KEY);
        $current = is_array($current) ? $current : [];

        $this->store([...$current, 'fonts' => array_values($fonts)]);
    }

    public function reset(): void
    {
        $current = Settings::get(self::KEY);
        $current = is_array($current) ? $current : [];
        $defaults = $this->defaults();

        $this->store(['fonts' => $current['fonts'] ?? [], 'version' => $current['version'] ?? 0, ...$defaults]);
    }

    public function store(array $value): void
    {
        $value['version'] = (int) ($value['version'] ?? 0) + 1;

        Setting::updateOrCreate(['key' => self::KEY], ['value' => $value]);
        Settings::flush();
        Cache::forget('theme:stylesheet');

        $this->publish();
    }

    public function contrast(array $theme): array
    {
        $palette = $this->palette($theme['variables']);
        $report = [];
        $modes = ($theme['style']['dark_mode'] ?? 'off') === 'auto' ? ['light', 'dark'] : ['light'];

        foreach (self::SLOTS as $key => $slot) {
            if (! $slot[5]) {
                continue;
            }

            foreach ($modes as $mode) {
                $fg = $this->slotColor($theme, $key, $mode, $palette);
                $bg = $this->slotColor($theme, $slot[5], $mode, $palette);
                $fgRgb = Color::parse($fg);
                $bgRgb = Color::parse($bg);

                if (! $fgRgb || ! $bgRgb || $fgRgb[3] == 0 || $bgRgb[3] == 0) {
                    continue;
                }

                $ratio = round(Color::contrast($fgRgb, $bgRgb), 2);
                $report[$key][$mode] = [
                    'ratio' => $ratio,
                    'level' => $ratio >= 7 ? 'AAA' : ($ratio >= 4.5 ? 'AA' : ($ratio >= 3 ? 'Large text only' : 'Hard to read')),
                    'on' => $slot[5],
                ];
            }
        }

        return $report;
    }

    public function css(array $theme, bool $allFonts = false, bool $forceDark = false): string
    {
        $palette = $this->palette($theme['variables']);
        $typography = $theme['typography'];
        $style = $theme['style'];
        $fonts = $this->fonts->all();
        $used = array_unique([$typography['heading_font'], $typography['body_font']]);

        $css = "@layer theme, base, components, utilities;\n";
        $css .= $this->fonts->faces($allFonts ? $fonts : array_filter($fonts, fn ($font) => in_array($font['id'], $used, true)));

        $root = [];
        foreach ($palette as $name => $value) {
            if ($name !== 'transparent') {
                $root[] = "--theme-{$name}: {$value};";
            }
        }
        foreach ($theme['slots'] as $key => $slot) {
            $root[] = "--slot-{$key}: ".$this->reference($slot['light']).';';
        }
        $root[] = '--slot-font-heading: '.$this->fonts->stack($typography['heading_font']).';';
        $root[] = '--slot-font-body: '.$this->fonts->stack($typography['body_font']).';';
        $root[] = "--slot-heading-weight: {$typography['heading_weight']};";
        $root[] = "--slot-font-size: {$typography['base_size']}px;";
        $root[] = '--slot-radius: '.self::RADII[$style['radius']][1].';';

        $css .= ":root {\n  ".implode("\n  ", $root)."\n}\n";

        if ($style['dark_mode'] === 'auto') {
            $dark = [];
            foreach ($theme['slots'] as $key => $slot) {
                if ($slot['dark'] !== null) {
                    $dark[] = "--slot-{$key}: ".$this->reference($slot['dark']).';';
                }
            }
            if ($dark && $forceDark) {
                $css .= ":root {\n  color-scheme: dark;\n  ".implode("\n  ", $dark)."\n}\n";
            } elseif ($dark) {
                $css .= "@media (prefers-color-scheme: dark) {\n  :root {\n    color-scheme: dark;\n    ".implode("\n    ", $dark)."\n  }\n}\n";
            }
        }

        $css .= <<<'CSS'
@layer base {
  html:has(> body.site-theme) { font-size: var(--slot-font-size); }
  .site-theme { background-color: var(--slot-page); color: var(--slot-text); font-family: var(--slot-font-body); }
  .site-theme :is(h1, h2, h3, h4, h5, h6) { font-family: var(--slot-font-heading); font-weight: var(--slot-heading-weight); color: var(--slot-heading); }
  .site-theme a:not([class]) { color: var(--slot-link); text-decoration: underline; text-underline-offset: 3px; }
  .site-theme a:not([class]):hover { color: var(--slot-link-hover); }
  .site-theme hr { border-color: var(--slot-border); }
  .site-theme ::selection { background-color: var(--slot-button); color: var(--slot-button-text); }
  .site-theme :focus-visible { outline-color: var(--slot-focus); }
}

CSS;

        $utilities = [];
        foreach (array_keys($palette) as $name) {
            if ($name === 'transparent') {
                continue;
            }
            $var = "var(--theme-{$name})";
            $utilities[] = ".bg-theme-{$name}, .hover\\:bg-theme-{$name}:hover { background-color: {$var}; }";
            $utilities[] = ".text-theme-{$name}, .hover\\:text-theme-{$name}:hover { color: {$var}; }";
            $utilities[] = ".border-theme-{$name} { border-color: {$var}; }";
        }
        $css .= "@layer utilities {\n  ".implode("\n  ", $utilities)."\n}\n";

        return $css;
    }

    public function stylesheetUrl(): string
    {
        $path = $this->stylesheetPath($this->get()['version']);

        if (! Storage::disk('public')->exists($path)) {
            $this->publish();
        }

        return Storage::disk('public')->url($path);
    }

    public function fontLinks(bool $allFonts = false): array
    {
        $theme = $this->get();
        $used = [$theme['typography']['heading_font'], $theme['typography']['body_font']];

        return $this->fonts->googleLinks($allFonts ? null : $used);
    }

    public function publish(): void
    {
        $theme = $this->get();
        $disk = Storage::disk('public');
        $path = $this->stylesheetPath($theme['version']);

        foreach ($disk->files('theme') as $old) {
            if ($old !== $path && Str::startsWith(basename($old), 'site-')) {
                $disk->delete($old);
            }
        }

        $disk->put($path, "/* Generated from Admin → Appearance. Changes here are overwritten. */\n".$this->css($theme));
    }

    private function stylesheetPath(int $version): string
    {
        return "theme/site-{$version}.css";
    }

    private function reference(string $name): string
    {
        return $name === 'transparent' ? 'transparent' : "var(--theme-{$name})";
    }

    private function slotColor(array $theme, string $key, string $mode, array $palette): ?string
    {
        $slot = $theme['slots'][$key] ?? null;
        $name = $mode === 'dark' ? ($slot['dark'] ?? $slot['light']) : $slot['light'];

        return $palette[$name] ?? null;
    }
}
