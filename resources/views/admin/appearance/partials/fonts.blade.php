@php
    use App\Services\Theme\Fonts;
    use App\Services\Theme\Theme;
    use Illuminate\Support\Js;

    $fontService = app(Fonts::class);
    $choices = [
        ['id' => 'system', 'family' => 'System sans', 'note' => 'Device default · fastest'],
        ['id' => 'serif-system', 'family' => 'System serif', 'note' => 'Device default'],
        ...array_map(
            fn ($font) => [
                'id' => $font['id'],
                'family' => $font['family'],
                'note' => $font['source'] === 'google' ? (empty($font['self_hosted']) ? 'Google Fonts' : 'Google · served here') : 'Uploaded',
            ],
            $fonts,
        ),
    ];
    $weightNames = [100 => 'Thin', 200 => 'Extra light', 300 => 'Light', 400 => 'Regular', 500 => 'Medium', 600 => 'Semibold', 700 => 'Bold', 800 => 'Extra bold', 900 => 'Black'];
    $fallbacks = collect(Theme::FALLBACKS)
        ->map(fn ($f) => $f[0])
        ->all();
    $typography = $theme['typography'];
    $usedFor = fn ($id) => array_keys(array_filter(['Headings' => $typography['heading_font'] === $id, 'Body text' => $typography['body_font'] === $id]));
    $addTab = old('google_family') !== null || $errors->has('google_family') || $errors->has('google_weights') ? 'google' : 'upload';
@endphp

<div class="space-y-5">
    <x-admin.card title="Fonts on the site" text="Pick a font for headings and one for everything else. Add more fonts below." icon="file-text">
        @foreach (['heading_font' => 'Headings', 'body_font' => 'Body text'] as $field => $label)
            <fieldset class="{{ $loop->first ? '' : 'mt-6' }}">
                <legend class="mb-2 text-sm font-semibold text-slate-800 dark:text-slate-100">{{ $label }}</legend>
                <div class="grid grid-cols-2 gap-2 sm:grid-cols-3">
                    @foreach ($choices as $choice)
                        <label
                            class="group/card relative flex cursor-pointer flex-col rounded-xl border border-slate-200 p-3 transition hover:border-slate-300 has-checked:border-blue-500! has-checked:bg-blue-50/60 has-checked:ring-1 has-checked:ring-blue-500 has-focus-visible:ring-3 has-focus-visible:ring-blue-500/30 dark:border-slate-700 dark:hover:border-slate-600 dark:has-checked:border-blue-400! dark:has-checked:bg-blue-500/10"
                        >
                            <input
                                type="radio"
                                class="sr-only"
                                value="{{ $choice['id'] }}"
                                x-model="values.typography.{{ $field }}"
                                x-bind:disabled="! canEdit"
                                @checked($typography[$field] === $choice['id'])
                            />
                            <span
                                class="truncate text-2xl leading-tight text-slate-900 dark:text-white"
                                style="font-family: {{ $fontService->stack($choice['id']) }}"
                            >
                                {{ $field === 'heading_font' ? 'Aa Bb' : 'Aa Bb Cc' }}
                            </span>
                            <span class="mt-1.5 truncate text-sm font-medium text-slate-800 dark:text-slate-100">{{ $choice['family'] }}</span>
                            <span class="truncate text-xs text-slate-500 dark:text-slate-400">{{ $choice['note'] }}</span>
                            <x-admin.icon
                                name="check-circle"
                                class="absolute top-2.5 right-2.5 hidden h-4 w-4 text-blue-600 group-has-checked/card:block dark:text-blue-400"
                            />
                        </label>
                    @endforeach
                </div>
                <p
                    class="mt-1.5 text-xs text-red-600 dark:text-red-400"
                    x-show="error('typography.{{ $field }}')"
                    x-cloak
                    x-text="error('typography.{{ $field }}')"
                ></p>
            </fieldset>
        @endforeach

        <div class="mt-6 grid gap-6 border-t border-slate-100 pt-5 sm:grid-cols-2 dark:border-slate-800">
            <div>
                <p class="mb-2 text-sm font-semibold text-slate-800 dark:text-slate-100">Heading weight</p>
                <div class="flex flex-wrap gap-1.5">
                    <template x-for="weight in (weights[values.typography.heading_font] ?? [])" :key="weight">
                        <button
                            type="button"
                            x-on:click="canEdit && (values.typography.heading_weight = weight)"
                            x-bind:class="
                                Number(values.typography.heading_weight) === weight
                                    ? 'border-blue-500 bg-blue-600 text-white dark:border-blue-400 dark:bg-blue-500'
                                    : 'border-slate-200 bg-white text-slate-700 hover:border-slate-300 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200'
                            "
                            class="tabular h-8 cursor-pointer rounded-lg border px-2.5 text-xs font-semibold transition"
                            x-bind:title="{{ Js::from($weightNames) }}[weight]"
                            x-text="weight"
                        ></button>
                    </template>
                </div>
                <p class="mt-1.5 text-xs text-slate-500 dark:text-slate-400">Only weights the heading font has are shown.</p>
            </div>

            <div>
                <p class="mb-2 flex items-center justify-between text-sm font-semibold text-slate-800 dark:text-slate-100">
                    Text size
                    <span
                        class="tabular text-xs font-medium text-slate-500 dark:text-slate-400"
                        x-text="values.typography.base_size + ' px'"
                    ></span>
                </p>
                <input
                    type="range"
                    min="14"
                    max="20"
                    step="1"
                    x-model.number="values.typography.base_size"
                    x-bind:disabled="! canEdit"
                    class="w-full accent-blue-600"
                    aria-label="Base text size"
                />
                <div class="flex justify-between text-[11px] text-slate-400">
                    <span>Smaller</span>
                    <span>16 px is standard</span>
                    <span>Larger</span>
                </div>
            </div>
        </div>
    </x-admin.card>

    <x-admin.card title="Font library" text="Every font you can choose above." icon="layers" :padded="false">
        @if ($canEdit)
            <p
                class="flex items-center gap-2 border-b border-amber-100 bg-amber-50 px-5 py-2.5 text-xs text-amber-800 dark:border-amber-500/20 dark:bg-amber-500/10 dark:text-amber-200"
                x-show="dirty"
                x-cloak
            >
                <x-admin.icon name="alert-triangle" class="h-3.5 w-3.5 shrink-0" />
                Save or discard your changes first. Adding or removing fonts reloads the page.
            </p>
        @endif

        @if ($fonts === [])
            <div class="px-5 py-10 text-center">
                <span
                    class="mx-auto flex h-11 w-11 items-center justify-center rounded-xl border border-slate-200 text-slate-400 dark:border-slate-700"
                >
                    <x-admin.icon name="file-text" class="h-5 w-5" />
                </span>
                <p class="mt-3 text-sm font-medium text-slate-800 dark:text-slate-100">No fonts added yet</p>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    The site uses the device's own fonts. Upload your brand font or add one from Google below.
                </p>
            </div>
        @else
            <fieldset x-bind:disabled="dirty" x-bind:class="dirty && 'opacity-60'">
                <ul class="divide-y divide-slate-100 dark:divide-slate-800">
                    @foreach ($fonts as $font)
                        @php
                            $uses = $usedFor($font['id']);
                            $isGoogle = $font['source'] === 'google';
                            $hosted = ! empty($font['self_hosted']);
                        @endphp

                        <li class="px-5 py-4" x-data="{ files: false }">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p
                                        class="truncate text-2xl text-slate-900 dark:text-white"
                                        style="font-family: {{ $fontService->stack($font['id']) }}"
                                    >
                                        The quick brown fox
                                    </p>
                                    <p class="mt-1 flex flex-wrap items-center gap-1.5 text-sm">
                                        <span class="font-semibold text-slate-800 dark:text-slate-100">{{ $font['family'] }}</span>
                                        <x-admin.status-badge
                                            :tone="$isGoogle ? 'info' : 'brand'"
                                            :label="$isGoogle ? ($hosted ? 'Google · served here' : 'Google Fonts') : 'Uploaded'"
                                        />
                                        @foreach ($uses as $use)
                                            <x-admin.status-badge tone="success" :label="$use" />
                                        @endforeach
                                    </p>
                                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                                        {{ collect($font['weights'])->sort()->implode(' · ') }}{{ $font['italic'] ? ' · italics' : '' }} · falls
                                        back to {{ strtolower(Theme::FALLBACKS[$font['fallback']][0] ?? 'sans-serif') }}
                                        @if ($hosted)
                                                · {{ count($font['files']) }} {{ str('file')->plural(count($font['files'])) }}
                                        @endif
                                    </p>
                                </div>

                                @if ($canEdit)
                                    <div class="flex shrink-0 flex-wrap items-center gap-1.5">
                                        @if (! $isGoogle)
                                            <x-admin.button type="button" size="sm" variant="secondary" icon="upload" x-on:click="files = ! files">
                                                Files
                                            </x-admin.button>
                                        @else
                                            <x-admin.confirm-button
                                                :action="route('admin.appearance.fonts.hosting', $font['id'])"
                                                :title="$hosted ? 'Load “'.$font['family'].'” from Google again?' : 'Serve “'.$font['family'].'” from this site?'"
                                                :message="$hosted ? 'The downloaded files are deleted and visitors load the font from Google Fonts.' : 'The font files are downloaded once and served from your own site: faster, and visitors\' browsers don\'t contact Google.'"
                                                :confirm="$hosted ? 'Load from Google' : 'Download & serve'"
                                                tone="primary"
                                                icon="download"
                                            >
                                                {{ $hosted ? 'Load from Google' : 'Serve from this site' }}
                                            </x-admin.confirm-button>
                                        @endif

                                        @if ($uses)
                                            <x-admin.button
                                                type="button"
                                                size="sm"
                                                variant="ghost"
                                                icon="trash"
                                                icon-only
                                                disabled
                                                aria-label="Delete font"
                                                title="Used for {{ strtolower(implode(' and ', $uses)) }}: choose another font there first"
                                            />
                                        @else
                                            <x-admin.confirm-button
                                                :action="route('admin.appearance.fonts.destroy', $font['id'])"
                                                method="DELETE"
                                                :title="'Delete “'.$font['family'].'”?'"
                                                :message="$isGoogle && ! $hosted ? 'It is removed from the library.' : 'It is removed from the library and its files are deleted.'"
                                                confirm="Delete"
                                                icon="trash"
                                                variant="ghost"
                                                aria-label="Delete font"
                                            />
                                        @endif
                                    </div>
                                @endif
                            </div>

                            @if (! $isGoogle && $canEdit)
                                <div
                                    x-show="files"
                                    x-cloak
                                    x-transition.opacity
                                    class="mt-4 rounded-xl border border-slate-200 bg-slate-50/60 p-4 dark:border-slate-700 dark:bg-slate-800/40"
                                >
                                    <ul class="mb-4 divide-y divide-slate-200/70 text-sm dark:divide-slate-700">
                                        @foreach ($font['files'] as $index => $file)
                                            <li class="flex items-center justify-between gap-3 py-1.5">
                                                <span
                                                    style="
                                                        font-family: '{{ $font['family'] }}';
                                                        font-weight: {{ $file['weight'] }};
                                                        font-style: {{ $file['style'] }};
                                                    "
                                                    class="text-slate-800 dark:text-slate-100"
                                                >
                                                    {{ $weightNames[$file['weight']] ?? $file['weight'] }}
                                                    {{ $file['style'] === 'italic' ? 'italic' : '' }}
                                                </span>
                                                <span class="flex items-center gap-2">
                                                    <span class="font-mono text-xs text-slate-400 uppercase">
                                                        {{ pathinfo($file['path'], PATHINFO_EXTENSION) }}
                                                    </span>
                                                    @if (count($font['files']) > 1)
                                                        <x-admin.confirm-button
                                                            :action="route('admin.appearance.fonts.files.destroy', [$font['id'], $index])"
                                                            method="DELETE"
                                                            title="Remove this file?"
                                                            :message="($weightNames[$file['weight']] ?? $file['weight']).' '.$file['style'].' is removed from “'.$font['family'].'”.'"
                                                            confirm="Remove"
                                                            icon="x"
                                                            variant="ghost"
                                                            size="xs"
                                                            aria-label="Remove file"
                                                        />
                                                    @endif
                                                </span>
                                            </li>
                                        @endforeach
                                    </ul>
                                    @include('admin.appearance.partials.font-files', ['action' => route('admin.appearance.fonts.files.store', $font['id']), 'submit' => 'Add files', 'withFamily' => false])
                                </div>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </fieldset>
        @endif
    </x-admin.card>

    @if ($canEdit)
        <x-admin.card title="Add a font" icon="plus" x-data="{ source: {{ Js::from($addTab) }} }">
            <fieldset x-bind:disabled="dirty" x-bind:class="dirty && 'opacity-60'">
                <div class="mb-5 inline-flex rounded-lg bg-slate-100 p-1 dark:bg-slate-800">
                    @foreach (['upload' => ['Upload files', 'upload'], 'google' => ['Google Fonts', 'globe']] as $key => [$label, $icon])
                        <button
                            type="button"
                            x-on:click="source = {{ Js::from($key) }}"
                            x-bind:class="
                                source === {{ Js::from($key) }}
                                    ? 'bg-white text-slate-900 shadow-xs dark:bg-slate-900 dark:text-white'
                                    : 'text-slate-500 hover:text-slate-800 dark:text-slate-400 dark:hover:text-white'
                            "
                            class="inline-flex cursor-pointer items-center gap-1.5 rounded-md px-3 py-1.5 text-sm font-medium transition"
                        >
                            <x-admin.icon :name="$icon" class="h-4 w-4" />
                            {{ $label }}
                        </button>
                    @endforeach
                </div>

                <div x-show="source === 'upload'">
                    <p class="mb-4 text-sm text-slate-500 dark:text-slate-400">
                        For fonts that aren't on Google, like the one your brand bought. Upload one file per weight/style. WOFF2 is smallest and loads
                        fastest.
                    </p>
                    @include('admin.appearance.partials.font-files', ['action' => route('admin.appearance.fonts.upload'), 'submit' => 'Add font', 'withFamily' => true, 'fallbacks' => $fallbacks])
                </div>

                <form
                    x-show="source === 'google'"
                    x-cloak
                    method="POST"
                    action="{{ route('admin.appearance.fonts.google') }}"
                    class="space-y-4"
                    x-data="{ submitting: false }"
                    x-on:submit="submitting = true"
                >
                    @csrf
                    <p class="text-sm text-slate-500 dark:text-slate-400">
                        Find a font on
                        <a
                            href="https://fonts.google.com"
                            target="_blank"
                            rel="noopener"
                            class="font-medium text-blue-600 hover:underline dark:text-blue-400"
                        >
                            fonts.google.com
                        </a>
                        and type its name exactly as shown there.
                    </p>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <x-admin.form.input name="google_family" label="Font name" placeholder="e.g. Playfair Display" required />
                        <x-admin.form.select
                            name="google_fallback"
                            label="If it can't load, use"
                            :options="$fallbacks"
                            :value="old('google_fallback', 'sans-serif')"
                        />
                    </div>

                    <div>
                        <p class="mb-2 text-sm font-medium text-slate-700 dark:text-slate-200">Weights</p>
                        <div class="flex flex-wrap gap-1.5">
                            @foreach ($weightNames as $weight => $name)
                                <label class="cursor-pointer">
                                    <input
                                        type="checkbox"
                                        name="google_weights[]"
                                        value="{{ $weight }}"
                                        class="peer sr-only"
                                        @checked(in_array($weight, old('google_weights', [400, 700])))
                                    />
                                    <span
                                        class="inline-flex h-8 items-center gap-1 rounded-lg border border-slate-200 px-2.5 text-xs font-medium text-slate-600 transition peer-checked:border-blue-500 peer-checked:bg-blue-600 peer-checked:text-white peer-focus-visible:ring-3 peer-focus-visible:ring-blue-500/30 hover:border-slate-300 dark:border-slate-700 dark:text-slate-300"
                                    >
                                        <span class="tabular">{{ $weight }}</span>
                                        <span class="opacity-70">{{ $name }}</span>
                                    </span>
                                </label>
                            @endforeach
                        </div>
                        <x-admin.form.error for="google_weights" />
                        <p class="mt-1.5 text-xs text-slate-500 dark:text-slate-400">
                            Pick only what you need: each weight makes the site a little slower to load.
                        </p>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2">
                        <x-admin.form.toggle name="google_italic" label="Include italics" :checked="(bool) old('google_italic', false)" />
                        <x-admin.form.toggle
                            name="google_self_host"
                            label="Serve from this site"
                            hint="Faster, and visitors' browsers don't contact Google."
                            :checked="(bool) old('google_self_host', true)"
                        />
                    </div>

                    <div class="flex justify-end">
                        <x-admin.button icon="plus">
                            <span x-text="submitting ? 'Checking Google Fonts…' : 'Add font'">Add font</span>
                        </x-admin.button>
                    </div>
                </form>
            </fieldset>
        </x-admin.card>
    @endif
</div>
