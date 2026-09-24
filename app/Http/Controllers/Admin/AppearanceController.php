<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Theme\Color;
use App\Services\Theme\Fonts;
use App\Services\Theme\Theme;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class AppearanceController extends Controller
{
    public function __construct(private Theme $theme, private Fonts $fonts) {}

    public function index(Request $request)
    {
        Gate::authorize('admin.appearance.view');

        $theme = $this->theme->get();
        $tabs = ['colors', 'slots', 'fonts', 'style', 'developers'];

        return view('admin.appearance.index', [
            'theme' => $theme,
            'fonts' => $theme['fonts'],
            'state' => $this->stateFor($theme),
            'tab' => in_array($request->query('tab'), $tabs, true) ? $request->query('tab') : 'colors',
            'canEdit' => $request->user()->can('admin.appearance.update'),
        ]);
    }

    public function check(Request $request)
    {
        Gate::authorize('admin.appearance.view');

        [$clean, $errors] = $this->theme->validate($request->all());
        $theme = $this->previewable($clean, $errors);

        return response()->json([
            'css' => $this->theme->css($theme, allFonts: true, forceDark: $request->boolean('dark')),
            ...$this->paletteData($theme),
            'contrast' => $this->theme->contrast($theme),
            'weights' => $this->fontWeights(),
            'errors' => $errors,
        ]);
    }

    public function update(Request $request)
    {
        Gate::authorize('admin.appearance.update');

        [$clean, $errors] = $this->theme->validate($request->all());

        if ($errors) {
            return response()->json(['message' => 'Some values need fixing before saving.', 'errors' => $errors], 422);
        }

        $this->theme->save($clean);

        return response()->json([
            'message' => 'Appearance saved. The website uses it now.',
            'state' => $this->stateFor($this->theme->get()),
        ]);
    }

    public function reset()
    {
        Gate::authorize('admin.appearance.update');

        $this->theme->reset();

        return to_route('admin.appearance.index')->with('success', 'Colours, fonts and style are back to the defaults. Your font library was kept.');
    }

    public function preview(Request $request)
    {
        Gate::authorize('admin.appearance.view');

        return response()
            ->view('admin.appearance.preview', [
                'css' => $this->theme->css($this->theme->get(), allFonts: true, forceDark: $request->boolean('dark')),
                'fontLinks' => $this->theme->fontLinks(allFonts: true),
            ])
            ->header('X-Frame-Options', 'SAMEORIGIN');
    }

    public function storeUploadedFont(Request $request)
    {
        Gate::authorize('admin.appearance.update');

        $data = $request->validate([
            'family' => ['required', 'string', 'regex:'.Fonts::FAMILY_PATTERN],
            'fallback' => ['required', Rule::in(array_keys(Theme::FALLBACKS))],
            ...$this->fileRules(),
        ], $this->fileMessages() + [
            'family.regex' => 'Use letters, numbers, spaces and dashes (e.g. Gilroy or Brand Sans).',
        ]);

        if ($this->fonts->familyTaken($data['family'])) {
            throw ValidationException::withMessages(['family' => "“{$data['family']}” is already in the font library. Add files to it there instead."]);
        }

        $font = $this->withFontErrors(fn () => $this->fonts->fromUploads($data['family'], $data['fallback'], $this->uploads($request)));
        $this->theme->saveFonts([...$this->fonts->all(), $font]);

        return $this->toFonts("“{$font['family']}” added with ".count($font['files']).' '.str('file')->plural(count($font['files'])).'. Choose it for headings or body text under “Fonts on the site”.');
    }

    public function addFontFiles(Request $request, string $font)
    {
        Gate::authorize('admin.appearance.update');

        $existing = $this->fontOr404($font);
        abort_unless($existing['source'] === 'upload', 404);

        $request->validate($this->fileRules(), $this->fileMessages());

        $updated = $this->withFontErrors(fn () => $this->fonts->fromUploads($existing['family'], $existing['fallback'], $this->uploads($request), $existing));
        $this->replaceFont($updated);

        return $this->toFonts("Files added to “{$existing['family']}”.");
    }

    public function destroyFontFile(string $font, int $index)
    {
        Gate::authorize('admin.appearance.update');

        $existing = $this->fontOr404($font);
        abort_unless($existing['source'] === 'upload' && isset($existing['files'][$index]), 404);

        if (count($existing['files']) === 1) {
            return $this->toFonts('That is the family’s only file. Delete the whole font instead.', 'error');
        }

        Storage::disk('public')->delete($existing['files'][$index]['path']);
        array_splice($existing['files'], $index, 1);
        $existing['weights'] = array_values(array_unique(array_column($existing['files'], 'weight')));
        $existing['italic'] = in_array('italic', array_column($existing['files'], 'style'), true);
        $this->replaceFont($existing);

        return $this->toFonts('Font file removed.');
    }

    public function storeGoogleFont(Request $request)
    {
        Gate::authorize('admin.appearance.update');

        $data = $request->validate([
            'google_family' => ['required', 'string', 'regex:'.Fonts::FAMILY_PATTERN],
            'google_weights' => ['required', 'array', 'min:1'],
            'google_weights.*' => ['integer', Rule::in(Fonts::WEIGHTS)],
            'google_italic' => ['boolean'],
            'google_fallback' => ['required', Rule::in(array_keys(Theme::FALLBACKS))],
            'google_self_host' => ['boolean'],
        ], [
            'google_family.required' => 'Type the font name as shown on fonts.google.com.',
            'google_family.regex' => 'Use the name as shown on fonts.google.com: letters, numbers, spaces and dashes.',
            'google_weights.required' => 'Tick at least one weight.',
        ]);

        $family = trim(preg_replace('/\s+/', ' ', $data['google_family']));

        if ($this->fonts->familyTaken($family)) {
            throw ValidationException::withMessages(['google_family' => "“{$family}” is already in the font library."]);
        }

        $font = $this->withFontErrors(fn () => $this->fonts->fromGoogle(
            $family,
            $data['google_weights'],
            (bool) ($data['google_italic'] ?? false),
            $data['google_fallback'],
            (bool) ($data['google_self_host'] ?? false),
        ), 'google_family');

        $this->theme->saveFonts([...$this->fonts->all(), $font]);

        return $this->toFonts("“{$family}” added from Google Fonts".($font['self_hosted'] ? ' and is served from this site.' : '.'));
    }

    public function toggleHosting(string $font)
    {
        Gate::authorize('admin.appearance.update');

        $existing = $this->fontOr404($font);
        abort_unless($existing['source'] === 'google', 404);

        $hosted = ! empty($existing['self_hosted']);
        $updated = $hosted
            ? $this->fonts->unhost($existing)
            : $this->withFontErrors(fn () => $this->fonts->selfHost($existing), 'hosting');

        $this->replaceFont($updated);

        return $this->toFonts($hosted
            ? "“{$existing['family']}” now loads from Google Fonts."
            : "“{$existing['family']}” is now served from this site (".count($updated['files']).' files).');
    }

    public function destroyFont(string $font)
    {
        Gate::authorize('admin.appearance.update');

        $existing = $this->fontOr404($font);
        $typography = $this->theme->get()['typography'];
        $uses = array_keys(array_filter([
            'headings' => $typography['heading_font'] === $font,
            'body text' => $typography['body_font'] === $font,
        ]));

        if ($uses) {
            return $this->toFonts("“{$existing['family']}” is used for ".implode(' and ', $uses).'. Choose another font there and save first.', 'error');
        }

        $this->fonts->deleteFiles($existing);
        $this->theme->saveFonts(array_filter($this->fonts->all(), fn ($f) => $f['id'] !== $font));

        return $this->toFonts("“{$existing['family']}” deleted.");
    }

    private function stateFor(array $theme): array
    {
        return [
            'values' => [
                'variables' => $theme['variables'],
                'slots' => $theme['slots'],
                'typography' => $theme['typography'],
                'style' => $theme['style'],
            ],
            ...$this->paletteData($theme),
            'contrast' => $this->theme->contrast($theme),
            'weights' => $this->fontWeights(),
        ];
    }

    private function paletteData(array $theme): array
    {
        $palette = $this->theme->palette($theme['variables']);

        return [
            'palette' => $palette,
            'hex' => array_map(fn ($value) => Color::toHex(Color::parse($value) ?? [0, 0, 0, 1]), $palette),
        ];
    }

    private function fontWeights(): array
    {
        $ids = ['system', 'serif-system', ...array_column($this->fonts->all(), 'id')];

        return array_combine($ids, array_map(fn ($id) => $this->fonts->weights($id), $ids));
    }

    private function previewable(array $clean, array $errors): array
    {
        $defaults = $this->theme->defaults();

        foreach (['radius', 'dark_mode'] as $field) {
            if (isset($errors["style.$field"])) {
                $clean['style'][$field] = $defaults['style'][$field];
            }
        }
        foreach (['heading_font', 'body_font', 'heading_weight', 'base_size'] as $field) {
            if (isset($errors["typography.$field"])) {
                $clean['typography'][$field] = $defaults['typography'][$field];
            }
        }

        return $clean;
    }

    private function fileRules(): array
    {
        return [
            'files' => ['required', 'array', 'min:1', 'max:18'],
            'files.*' => ['file', 'max:'.Fonts::MAX_FILE_KB, 'extensions:woff2,woff,ttf,otf'],
            'weights' => ['required', 'array'],
            'weights.*' => ['required', 'integer', Rule::in(Fonts::WEIGHTS)],
            'styles' => ['required', 'array'],
            'styles.*' => ['required', Rule::in(['normal', 'italic'])],
        ];
    }

    private function fileMessages(): array
    {
        return [
            'files.required' => 'Choose at least one font file.',
            'files.max' => 'Upload at most 18 files at a time.',
            'files.*.max' => 'Each font file can be at most 5 MB.',
            'files.*.extensions' => 'Upload WOFF2, WOFF, TTF or OTF files.',
        ];
    }

    private function uploads(Request $request): array
    {
        $files = [];
        $seen = [];

        foreach ($request->file('files', []) as $i => $file) {
            $weight = (int) $request->input("weights.$i");
            $style = (string) $request->input("styles.$i");

            if (isset($seen["$weight-$style"])) {
                throw ValidationException::withMessages(['files' => "Two files are set to {$weight} {$style}. Give each file its own weight or style."]);
            }

            $seen["$weight-$style"] = true;
            $files[] = [$file, $weight, $style];
        }

        return $files;
    }

    private function withFontErrors(callable $callback, string $field = 'files')
    {
        try {
            return $callback();
        } catch (RuntimeException $e) {
            throw ValidationException::withMessages([$field => $e->getMessage()]);
        }
    }

    private function fontOr404(string $id): array
    {
        return $this->fonts->find($id) ?? abort(404);
    }

    private function replaceFont(array $font): void
    {
        $this->theme->saveFonts(array_map(fn ($f) => $f['id'] === $font['id'] ? $font : $f, $this->fonts->all()));
    }

    private function toFonts(string $message, string $type = 'success')
    {
        return to_route('admin.appearance.index', ['tab' => 'fonts'])->with($type, $message);
    }
}
