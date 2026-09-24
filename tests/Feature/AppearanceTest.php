<?php

use App\Helpers\Settings;
use App\Models\User;
use App\Services\Theme\Color;
use App\Services\Theme\Fonts;
use App\Services\Theme\Theme;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');
    Settings::flush();
});

function appearanceAdmin(bool $canEdit = true): User
{
    $role = Role::findOrCreate($canEdit ? 'super admin' : 'viewer', 'web');
    $role->givePermissionTo(Permission::findOrCreate('admin.appearance.view', 'web'));
    if ($canEdit) {
        $role->givePermissionTo(Permission::findOrCreate('admin.appearance.update', 'web'));
    }

    return tap(User::factory()->create())->assignRole($role);
}

function themePayload(array $changes = []): array
{
    $theme = app(Theme::class)->get();

    return array_replace_recursive([
        'variables' => $theme['variables'],
        'slots' => $theme['slots'],
        'typography' => $theme['typography'],
        'style' => $theme['style'],
    ], $changes);
}

function fontFile(string $name = 'Brand-Bold.woff2', string $magic = 'wOF2'): UploadedFile
{
    return UploadedFile::fake()->createWithContent($name, $magic.str_repeat("\0", 64));
}

test('colours are parsed from every supported format', function () {
    expect(Color::toHex(Color::parse('#4f46e5')))->toBe('#4f46e5')
        ->and(Color::toHex(Color::parse('#FFF')))->toBe('#ffffff')
        ->and(Color::toHex(Color::parse('rgb(255 0 0 / 50%)')))->toBe('#ff0000')
        ->and(Color::parse('rgba(0, 0, 0, .5)')[3])->toBe(0.5)
        ->and(Color::toHex(Color::parse('hsl(210 40% 50%)')))->toBe('#4d80b3')
        ->and(Color::isValid('oklch(62% 0.2 264)'))->toBeTrue()
        ->and(Color::isValid('transparent'))->toBeTrue();

    foreach (['', 'banana', '#12345', 'rgb(300, 0, 0)', 'hsl(0 150% 50%)', 'red; } body { display:none', 'url(x)'] as $bad) {
        expect(Color::isValid($bad))->toBeFalse();
    }
});

test('shades run from light to dark and contrast follows WCAG', function () {
    $shades = Color::shades('#4f46e5');
    $lightness = array_map(fn ($css) => (float) substr($css, 6), $shades);

    expect(array_keys($shades))->toBe(Color::SHADES)
        ->and(array_values($lightness))->toBe(collect($lightness)->sortDesc()->values()->all())
        ->and(round(Color::contrast(Color::parse('#fff'), Color::parse('#000')), 2))->toBe(21.0)
        ->and(round(Color::contrast(Color::parse('#777'), Color::parse('#777')), 2))->toBe(1.0);
});

test('the page needs permission; view-only admins cannot save', function () {
    $this->actingAs(User::factory()->create())->get(route('admin.appearance.index'))->assertForbidden();

    $viewer = appearanceAdmin(canEdit: false);
    $this->actingAs($viewer)->get(route('admin.appearance.index'))->assertOk()->assertSee("don't have permission to save", false);
    $this->actingAs($viewer)->putJson(route('admin.appearance.update'), themePayload())->assertForbidden();
    $this->actingAs($viewer)->post(route('admin.appearance.reset'))->assertForbidden();
});

test('the editor page renders every tab', function () {
    $this->actingAs(appearanceAdmin())->get(route('admin.appearance.index', ['tab' => 'fonts']))
        ->assertOk()
        ->assertSee('Colour variables')->assertSee('Main button')->assertSee('Font library')
        ->assertSee('Dark mode')->assertSee('bg-site-button')
        ->assertSee(route('admin.appearance.preview'));
});

test('the live check returns css, palette, contrast and field errors', function () {
    $payload = themePayload();
    $payload['variables'][0]['value'] = '#e11d48';
    $payload['variables'][1]['value'] = 'not-a-colour';

    $response = $this->actingAs(appearanceAdmin())->postJson(route('admin.appearance.check'), $payload)
        ->assertOk()
        ->assertJsonPath('palette.primary', '#e11d48')
        ->assertJsonPath('hex.primary', '#e11d48')
        ->assertJsonStructure(['css', 'contrast' => ['button-text' => ['light' => ['ratio', 'level']]], 'weights' => ['system']])
        ->assertSee('--theme-primary: #e11d48', false);

    expect($response->json('errors'))->toBe([
        'variables.1.value' => 'Not a colour this site understands. Use #hex, rgb(), hsl() or oklch().',
        'slots.accent.light' => 'Pick a colour for “Accent”: “accent-400” doesn\'t exist any more.',
    ]);
});

test('saving validates names, values, shades and slots', function (string $path, mixed $value, string $field) {
    $payload = themePayload();
    data_set($payload, $path, $value);

    $this->actingAs(appearanceAdmin())->putJson(route('admin.appearance.update'), $payload)
        ->assertStatus(422)
        ->assertJsonStructure(['errors' => [$field]]);

    expect(Settings::get(Theme::KEY))->toBeNull();
})->with([
    'bad name' => ['variables.0.name', 'Footer BG', 'variables.0.name'],
    'duplicate name' => ['variables.1.name', 'primary', 'variables.1.name'],
    'reserved name' => ['variables.1.name', 'transparent', 'variables.1.name'],
    'css injection' => ['variables.0.value', '#fff; } body { display: none', 'variables.0.value'],
    'clashes with a shade' => ['variables.10', ['name' => 'primary-500', 'value' => '#000', 'shades' => false], 'variables.10.name'],
    'slot uses a missing colour' => ['slots.button.light', 'nope', 'slots.button.light'],
    'slot uses a shade that is turned off' => ['variables.0.shades', false, 'slots.button.light'],
    'bad radius' => ['style.radius', 'huge', 'style.radius'],
    'text size out of range' => ['typography.base_size', 40, 'typography.base_size'],
    'unknown font' => ['typography.heading_font', 'gone', 'typography.heading_font'],
]);

test('saving publishes a stylesheet the website links to', function () {
    $payload = themePayload();
    $payload['variables'][] = ['name' => 'footer-bg', 'value' => '#14532d', 'shades' => false];
    $payload['slots']['footer'] = ['light' => 'footer-bg', 'dark' => null];
    $payload['slots']['page'] = ['light' => 'white', 'dark' => 'ink'];
    $payload['style'] = ['radius' => 'full', 'dark_mode' => 'auto'];
    $payload['typography']['base_size'] = 18;

    $this->actingAs(appearanceAdmin())->putJson(route('admin.appearance.update'), $payload)
        ->assertOk()
        ->assertJsonPath('state.values.slots.footer.light', 'footer-bg');

    $css = collect(Storage::disk('public')->files('theme'))->map(fn ($f) => Storage::disk('public')->get($f))->implode('');

    expect(Storage::disk('public')->files('theme'))->toHaveCount(1)
        ->and($css)->toContain('--theme-footer-bg: #14532d;')
        ->toContain('--slot-footer: var(--theme-footer-bg);')
        ->toContain('--theme-primary-600: oklch(')
        ->toContain('--slot-radius: 9999px;')
        ->toContain('--slot-font-size: 18px;')
        ->toContain("@media (prefers-color-scheme: dark) {\n  :root {\n    color-scheme: dark;\n    --slot-page: var(--theme-ink);")
        ->toContain('.bg-theme-footer-bg, .hover\:bg-theme-footer-bg:hover { background-color: var(--theme-footer-bg); }')
        ->toStartWith('/* Generated');

    $this->get('/')->assertOk()
        ->assertSee(Storage::disk('public')->url(Storage::disk('public')->files('theme')[0]), false)
        ->assertSee('class="site-theme antialiased"', false);
});

test('each save replaces the old stylesheet, and a missing one is rebuilt', function () {
    $admin = appearanceAdmin();
    $this->actingAs($admin)->putJson(route('admin.appearance.update'), themePayload())->assertOk();
    $this->actingAs($admin)->putJson(route('admin.appearance.update'), themePayload(['style' => ['radius' => 'sm']]))->assertOk();

    $files = Storage::disk('public')->files('theme');
    expect($files)->toHaveCount(1);

    Storage::disk('public')->delete($files[0]);
    app(Theme::class)->stylesheetUrl();
    expect(Storage::disk('public')->files('theme'))->toHaveCount(1);
});

test('the admin panel does not get the website theme', function () {
    $this->actingAs(appearanceAdmin())->putJson(route('admin.appearance.update'), themePayload())->assertOk();

    $this->get(route('admin.appearance.index'))->assertDontSee('site-theme antialiased', false)->assertDontSee('/storage/theme/', false);
});

test('reset restores defaults but keeps the font library', function () {
    $admin = appearanceAdmin();
    $this->actingAs($admin)->post(route('admin.appearance.fonts.upload'), [
        'family' => 'Brand Sans', 'fallback' => 'sans-serif', 'files' => [fontFile()], 'weights' => [700], 'styles' => ['normal'],
    ])->assertSessionHasNoErrors();
    $this->putJson(route('admin.appearance.update'), themePayload(['style' => ['radius' => 'none']]))->assertOk();

    $this->post(route('admin.appearance.reset'))->assertRedirect(route('admin.appearance.index'));

    $theme = app(Theme::class)->get();
    expect($theme['style']['radius'])->toBe('md')->and(array_column($theme['fonts'], 'family'))->toBe(['Brand Sans']);
});

test('the preview page can be framed by the admin only', function () {
    $this->actingAs(appearanceAdmin())->get(route('admin.appearance.preview', ['dark' => 1]))
        ->assertOk()
        ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
        ->assertSee('id="theme-css"', false);

    $this->get('/')->assertHeader('X-Frame-Options', 'DENY');
});

test('fonts can be uploaded, and only real font files are accepted', function () {
    $admin = appearanceAdmin();

    $this->actingAs($admin)->post(route('admin.appearance.fonts.upload'), [
        'family' => 'Gilroy', 'fallback' => 'sans-serif',
        'files' => [fontFile('Gilroy-Regular.woff2'), fontFile('Gilroy-BoldItalic.ttf', "\x00\x01\x00\x00")],
        'weights' => [400, 700], 'styles' => ['normal', 'italic'],
    ])->assertRedirect(route('admin.appearance.index', ['tab' => 'fonts']))->assertSessionHas('success');

    $font = app(Fonts::class)->all()[0];
    expect($font['family'])->toBe('Gilroy')
        ->and($font['weights'])->toBe([400, 700])
        ->and($font['italic'])->toBeTrue()
        ->and(array_column($font['files'], 'format'))->toBe(['woff2', 'truetype']);
    Storage::disk('public')->assertExists($font['files'][0]['path']);

    $this->putJson(route('admin.appearance.update'), themePayload(['typography' => ['heading_font' => $font['id']]]))->assertOk();
    $css = Storage::disk('public')->get(Storage::disk('public')->files('theme')[0]);
    expect($css)->toContain("font-family: 'Gilroy';")->toContain("format('woff2')")->toContain("--slot-font-heading: 'Gilroy', ui-sans-serif");

    $before = count(Storage::disk('public')->allFiles('fonts'));
    $this->post(route('admin.appearance.fonts.upload'), [
        'family' => 'Fake', 'fallback' => 'serif', 'files' => [fontFile('fake.woff2', 'GIF8'), fontFile('ok.woff2')], 'weights' => [400, 700], 'styles' => ['normal', 'normal'],
    ])->assertSessionHasErrors(['files' => '“fake.woff2” is not a font file. Upload WOFF2, WOFF, TTF or OTF files.']);
    expect(count(Storage::disk('public')->allFiles('fonts')))->toBe($before);

    $this->post(route('admin.appearance.fonts.upload'), ['family' => 'gilroy', 'fallback' => 'serif', 'files' => [fontFile()], 'weights' => [400], 'styles' => ['normal']])
        ->assertSessionHasErrors('family');
    $this->post(route('admin.appearance.fonts.upload'), ['family' => 'Twice', 'fallback' => 'serif', 'files' => [fontFile(), fontFile()], 'weights' => [400, 400], 'styles' => ['normal', 'normal']])
        ->assertSessionHasErrors('files');
    $this->post(route('admin.appearance.fonts.upload'), ['family' => 'Bad', 'fallback' => 'serif', 'files' => [UploadedFile::fake()->create('x.exe', 5)], 'weights' => [400], 'styles' => ['normal']])
        ->assertSessionHasErrors('files.0');
});

test('files can be added to or removed from an uploaded family', function () {
    $admin = appearanceAdmin();
    $this->actingAs($admin)->post(route('admin.appearance.fonts.upload'), [
        'family' => 'Gilroy', 'fallback' => 'sans-serif', 'files' => [fontFile()], 'weights' => [400], 'styles' => ['normal'],
    ]);
    $font = app(Fonts::class)->all()[0];
    $oldPath = $font['files'][0]['path'];

    $this->post(route('admin.appearance.fonts.files.store', $font['id']), ['files' => [fontFile('a.woff'), fontFile('b.woff2')], 'weights' => [400, 800], 'styles' => ['normal', 'normal']])
        ->assertSessionHasNoErrors();
    Settings::flush();
    $font = app(Fonts::class)->all()[0];
    expect($font['weights'])->toBe([400, 800]);
    Storage::disk('public')->assertMissing($oldPath);

    $this->delete(route('admin.appearance.fonts.files.destroy', [$font['id'], 1]))->assertSessionHas('success');
    Settings::flush();
    $this->delete(route('admin.appearance.fonts.files.destroy', [$font['id'], 0]))->assertSessionHas('error');
    expect(app(Fonts::class)->all()[0]['files'])->toHaveCount(1);
});

test('fonts in use cannot be deleted; unused ones take their files with them', function () {
    $admin = appearanceAdmin();
    $this->actingAs($admin)->post(route('admin.appearance.fonts.upload'), [
        'family' => 'Gilroy', 'fallback' => 'sans-serif', 'files' => [fontFile()], 'weights' => [400], 'styles' => ['normal'],
    ]);
    $font = app(Fonts::class)->all()[0];
    $this->putJson(route('admin.appearance.update'), themePayload(['typography' => ['body_font' => $font['id']]]))->assertOk();

    $this->delete(route('admin.appearance.fonts.destroy', $font['id']))->assertSessionHas('error', '“Gilroy” is used for body text. Choose another font there and save first.');

    $this->putJson(route('admin.appearance.update'), themePayload(['typography' => ['body_font' => 'system']]))->assertOk();
    $this->delete(route('admin.appearance.fonts.destroy', $font['id']))->assertSessionHas('success');

    expect(app(Fonts::class)->all())->toBe([])->and(Storage::disk('public')->allFiles('fonts'))->toBe([]);
});

test('google fonts are checked with Google and can be served from this site', function () {
    Http::fake([
        'fonts.googleapis.com/*' => Http::response("@font-face {\n  font-family: 'Lora';\n  font-style: normal;\n  font-weight: 400;\n  src: url(https://fonts.gstatic.com/s/lora/v1/a.woff2) format('woff2');\n  unicode-range: U+0000-00FF;\n}\n@font-face {\n  font-family: 'Lora';\n  font-style: normal;\n  font-weight: 700;\n  src: url(https://fonts.gstatic.com/s/lora/v1/b.woff2) format('woff2');\n}"),
        'fonts.gstatic.com/*' => Http::response('wOF2'.str_repeat("\0", 32)),
    ]);
    $admin = appearanceAdmin();

    $this->actingAs($admin)->post(route('admin.appearance.fonts.google'), [
        'google_family' => 'Lora', 'google_weights' => [400, 700], 'google_italic' => 0, 'google_fallback' => 'serif', 'google_self_host' => 1,
    ])->assertSessionHas('success', '“Lora” added from Google Fonts and is served from this site.');

    $font = app(Fonts::class)->all()[0];
    expect($font['self_hosted'])->toBeTrue()->and($font['files'])->toHaveCount(2)->and($font['files'][0]['unicode_range'])->toBe('U+0000-00FF');
    Storage::disk('public')->assertExists($font['files'][1]['path']);

    $this->post(route('admin.appearance.fonts.hosting', $font['id']))->assertSessionHas('success');
    Settings::flush();
    expect(Storage::disk('public')->allFiles('fonts'))->toBe([]);
    $this->putJson(route('admin.appearance.update'), themePayload(['typography' => ['body_font' => $font['id']]]))->assertOk();
    $this->get('/')->assertSee('https://fonts.googleapis.com/css2?family=Lora:wght@400;700&amp;display=swap', false);

    Http::assertSent(fn ($request) => str_starts_with($request->url(), 'https://fonts.googleapis.com/css2?family=Lora:wght@400;700'));
});

test('google font problems are explained', function (array $responses, string $message) {
    Http::fake(['fonts.googleapis.com/*' => Http::sequence(array_map(fn ($r) => Http::response($r[1], $r[0]), $responses))]);

    $this->actingAs(appearanceAdmin())->post(route('admin.appearance.fonts.google'), [
        'google_family' => 'Lorra', 'google_weights' => [100], 'google_fallback' => 'serif',
    ])->assertSessionHasErrors(['google_family' => $message]);

    expect(app(Fonts::class)->all())->toBe([]);
})->with([
    'unknown family' => [[[400, ''], [400, '']], 'Google Fonts has no font called “Lorra”. Check the spelling and capitals exactly as on fonts.google.com (e.g. “Playfair Display”).'],
    'missing weight' => [[[400, ''], [200, '@font-face {}']], '“Lorra” doesn\'t come in all the weights you picked. Check the styles listed on fonts.google.com and pick those.'],
]);

test('an unreachable Google Fonts is reported', function () {
    Http::fake(fn () => throw new ConnectionException('offline'));

    $this->actingAs(appearanceAdmin())->post(route('admin.appearance.fonts.google'), [
        'google_family' => 'Lora', 'google_weights' => [400], 'google_fallback' => 'serif',
    ])->assertSessionHasErrors(['google_family' => "Couldn't reach Google Fonts from this server. Check its internet connection, or try again later."]);
});
