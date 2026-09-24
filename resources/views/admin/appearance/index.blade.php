@php
    use App\Services\Theme\Theme;
    use Illuminate\Support\Js;

    $slotMeta = collect(Theme::SLOTS)
        ->map(fn ($slot) => ['label' => $slot[1], 'group' => $slot[0]])
        ->all();
    $themeService = app(Theme::class);
    $fontFaces = app(\App\Services\Theme\Fonts::class)->faces($fonts);
    $googleLinks = $themeService->fontLinks(allFonts: true);

    $tabs = [
        'colors' => ['Colours', 'palette'],
        'slots' => ['Where colours go', 'layers'],
        'fonts' => ['Fonts', 'file-text'],
        'style' => ['Style', 'sliders'],
        'developers' => ['For developers', 'code'],
    ];
@endphp

<x-admin :breadcrumb="[['label' => 'Appearance', 'url' => route('admin.appearance.index')]]">
    @foreach ($googleLinks as $href)
        <link rel="stylesheet" href="{{ $href }}" />
    @endforeach

    @if ($fontFaces)
        <style>
            {!! $fontFaces !!}
        </style>
    @endif

    <div
        x-data="appearanceEditor(
                    {{
                        Js::from([
                            'state' => $state,
                            'urls' => ['check' => route('admin.appearance.check'), 'update' => route('admin.appearance.update')],
                            'slots' => $slotMeta,
                            'canEdit' => $canEdit,
                            'tab' => $tab,
                        ])
                    }},
                )"
        x-on:click.window="pickerOutside($event)"
        x-on:keydown.escape.window="closePicker()"
        x-on:submit.window="allowLeave()"
    >
        <x-admin.page-header
            title="Appearance"
            description="Colours, fonts and style of your public website. The admin panel keeps its own look."
            icon="palette"
        >
            <x-slot:actions>
                <x-admin.button variant="secondary" icon="external-link" :href="url('/')" target="_blank" rel="noopener">
                    View website
                </x-admin.button>
                @if ($canEdit)
                    <x-admin.confirm-button
                        :action="route('admin.appearance.reset')"
                        title="Reset the look to the defaults?"
                        message="Colours, where they are used, fonts on the site and style go back to the starting values. Fonts you added stay in the library."
                        confirm="Reset"
                        icon="refresh"
                        size="md"
                    >
                        Reset to defaults
                    </x-admin.confirm-button>
                @endif
            </x-slot>
        </x-admin.page-header>

        @unless ($canEdit)
            <div
                class="mb-5 flex items-start gap-2.5 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-300"
            >
                <x-admin.icon name="lock" class="mt-0.5 h-4 w-4 shrink-0" />
                <span>You can look around and try things in the preview, but you don't have permission to save changes.</span>
            </div>
        @endunless

        <div class="grid grid-cols-1 items-start gap-6 2xl:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]">
            <div class="min-w-0">
                <nav
                    class="scrollbar-hide mb-5 flex gap-1 overflow-x-auto shadow-[inset_0_-1px_0] shadow-slate-200 dark:shadow-slate-800"
                    aria-label="Appearance sections"
                >
                    @foreach ($tabs as $key => [$label, $icon])
                        <button
                            type="button"
                            x-on:click="tab = {{ Js::from($key) }}"
                            @if ($tab === $key) aria-current="page" @endif
                            x-bind:aria-current="tab === {{ Js::from($key) }} ? 'page' : null"
                            class="inline-flex shrink-0 cursor-pointer items-center gap-2 border-b-2 border-transparent px-3 py-2.5 text-sm font-medium whitespace-nowrap text-slate-500 transition hover:border-slate-300 hover:text-slate-900 aria-[current=page]:border-blue-600! aria-[current=page]:text-blue-700! dark:text-slate-400 dark:hover:border-slate-600 dark:hover:text-white dark:aria-[current=page]:border-blue-400! dark:aria-[current=page]:text-blue-300!"
                        >
                            <x-admin.icon :name="$icon" class="h-4 w-4" />
                            {{ $label }}
                            <span
                                x-show="tabErrors({{ Js::from($key) }})"
                                x-cloak
                                x-text="tabErrors({{ Js::from($key) }})"
                                class="tabular rounded-full bg-red-100 px-1.5 text-[11px] font-semibold text-red-700 dark:bg-red-500/15 dark:text-red-300"
                            ></span>
                        </button>
                    @endforeach
                </nav>

                @foreach (array_keys($tabs) as $key)
                    <div x-show="tab === {{ Js::from($key) }}" @if ($tab !== $key) x-cloak @endif>
                        @include('admin.appearance.partials.' . $key)
                    </div>
                @endforeach
            </div>

            @include('admin.appearance.partials.preview-panel')
        </div>

        @if ($canEdit)
            <div
                class="sticky bottom-3 z-20 mt-6 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-200/80 bg-white/90 px-4 py-3 shadow-sm backdrop-blur-md dark:border-slate-800 dark:bg-slate-900/90"
            >
                <p class="flex min-w-0 items-center gap-2 text-sm">
                    <template x-if="errorCount">
                        <span class="flex items-center gap-1.5 font-medium text-red-600 dark:text-red-400">
                            <x-admin.icon name="alert-circle" class="h-4 w-4" />
                            <span x-text="errorCount + (errorCount === 1 ? ' value needs fixing' : ' values need fixing')"></span>
                        </span>
                    </template>
                    <template x-if="! errorCount && dirty">
                        <span class="flex items-center gap-1.5 font-medium text-amber-700 dark:text-amber-300">
                            <span class="h-2 w-2 rounded-full bg-amber-500"></span>
                            Unsaved changes. The preview shows them; the website doesn't yet.
                        </span>
                    </template>
                    <template x-if="! errorCount && ! dirty">
                        <span class="flex items-center gap-1.5 text-slate-500 dark:text-slate-400">
                            <x-admin.icon name="check-circle" class="h-4 w-4 text-emerald-500" />
                            All changes are live on the website.
                        </span>
                    </template>
                </p>

                <div class="flex items-center gap-2">
                    <x-admin.button type="button" variant="ghost" x-show="dirty" x-cloak x-on:click="discard()">Discard</x-admin.button>
                    <x-admin.button type="button" icon="save" x-on:click="save()" x-bind:disabled="saving || ! dirty">
                        <span x-text="saving ? 'Saving…' : 'Save changes'">Save changes</span>
                    </x-admin.button>
                </div>
            </div>
        @endif

        @include('admin.appearance.partials.picker')
    </div>
</x-admin>
