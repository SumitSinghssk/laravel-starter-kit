@php
    use App\Services\Theme\Theme;
    use Illuminate\Support\Js;

    $groups = collect(Theme::SLOTS)->groupBy(fn ($slot) => $slot[0], preserveKeys: true);
    $groupIcons = ['Page' => 'file-text', 'Header' => 'panel-left', 'Buttons' => 'toggle', 'Highlights' => 'star', 'Forms' => 'pencil', 'Footer' => 'layers', 'Messages' => 'message'];
@endphp

<div class="space-y-5">
    <div
        class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-blue-100 bg-blue-50/60 px-4 py-3 text-sm text-slate-700 dark:border-blue-500/20 dark:bg-blue-500/5 dark:text-slate-200"
    >
        <p class="flex items-start gap-2">
            <x-admin.icon name="info" class="mt-0.5 h-4 w-4 shrink-0 text-blue-600 dark:text-blue-400" />
            <span>Each part of the website takes its colour from a variable. New colours you add appear in every list straight away.</span>
        </p>
        <p class="text-xs text-slate-500 dark:text-slate-400" x-show="values.style.dark_mode !== 'auto'">
            Dark mode is off.
            <button type="button" class="cursor-pointer font-semibold text-blue-600 hover:underline dark:text-blue-400" x-on:click="tab = 'style'">
                Turn it on
            </button>
            to pick dark colours too.
        </p>
    </div>

    @foreach ($groups as $group => $groupSlots)
        <x-admin.card :title="$group" :icon="$groupIcons[$group] ?? 'palette'" :padded="false">
            <div
                class="hidden gap-3 border-b border-slate-100 px-5 py-2 text-[11px] font-medium tracking-wide text-slate-400 uppercase sm:grid dark:border-slate-800"
                x-bind:class="
                    values.style.dark_mode === 'auto'
                        ? 'sm:grid-cols-[minmax(0,1fr)_11rem_11rem]'
                        : 'sm:grid-cols-[minmax(0,1fr)_11rem]'
                "
            >
                <span>Part of the site</span>
                <span>Colour</span>
                <span x-show="values.style.dark_mode === 'auto'">In dark mode</span>
            </div>

            <ul class="divide-y divide-slate-100 dark:divide-slate-800">
                @foreach ($groupSlots as $key => $slot)
                    <li
                        class="grid grid-cols-1 items-center gap-2 px-5 py-3 sm:gap-3"
                        x-bind:class="
                            values.style.dark_mode === 'auto'
                                ? 'sm:grid-cols-[minmax(0,1fr)_11rem_11rem]'
                                : 'sm:grid-cols-[minmax(0,1fr)_11rem]'
                        "
                    >
                        <div class="min-w-0">
                            <p class="flex flex-wrap items-center gap-2 text-sm font-medium text-slate-800 dark:text-slate-100">
                                {{ $slot[1] }}
                                @if ($slot[5])
                                    <template x-if="contrastFor({{ Js::from($key) }})">
                                        <span
                                            class="tabular inline-flex items-center gap-1 rounded-full px-1.5 py-0.5 text-[10.5px] font-semibold"
                                            x-bind:class="{
                                                'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300':
                                                    contrastFor({{ Js::from($key) }}).ratio >= 4.5,
                                                'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-300':
                                                    contrastFor({{ Js::from($key) }}).ratio >= 3 &&
                                                    contrastFor({{ Js::from($key) }}).ratio < 4.5,
                                                'bg-red-50 text-red-700 dark:bg-red-500/10 dark:text-red-300':
                                                    contrastFor({{ Js::from($key) }}).ratio < 3,
                                            }"
                                            x-bind:title="
                                                'Contrast on ' +
                                                    slots[contrastFor({{ Js::from($key) }}).on].label.toLowerCase() +
                                                    (previewDark ? ' (dark mode)' : '') +
                                                    '. 4.5 or more reads well for everyone.'
                                            "
                                            x-text="
                                                contrastFor({{ Js::from($key) }}).ratio.toFixed(1) +
                                                    ':1 · ' +
                                                    contrastFor({{ Js::from($key) }}).level
                                            "
                                        ></span>
                                    </template>
                                @endif
                            </p>
                            @if ($slot[2])
                                <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">{{ $slot[2] }}</p>
                            @endif

                            <p
                                class="mt-1 text-xs text-red-600 dark:text-red-400"
                                x-show="
                                    error({{ Js::from("slots.$key.light") }}) ||
                                        error({{ Js::from("slots.$key.dark") }})
                                "
                                x-cloak
                                x-text="
                                    error({{ Js::from("slots.$key.light") }}) ||
                                        error({{ Js::from("slots.$key.dark") }})
                                "
                            ></p>
                        </div>

                        @foreach (['light', 'dark'] as $mode)
                            <button
                                type="button"
                                data-picker-trigger
                                @if ($mode === 'dark') x-show="values.style.dark_mode === 'auto'" x-cloak @endif
                                x-on:click="openPicker($event, {{ Js::from($key) }}, {{ Js::from($mode) }})"
                                x-bind:class="
                                    picker.open &&
                                    picker.slot === {{ Js::from($key) }} &&
                                    picker.mode === {{ Js::from($mode) }}
                                        ? 'border-blue-400 ring-3 ring-blue-500/20'
                                        : 'border-slate-200 hover:border-slate-300 dark:border-slate-700'
                                "
                                x-bind:disabled="! canEdit"
                                class="flex h-9 w-full min-w-0 cursor-pointer items-center gap-2 rounded-lg border bg-white px-2 text-left text-sm shadow-xs transition disabled:cursor-default dark:bg-slate-900"
                                aria-label="{{ $slot[1] }}{{ $mode === 'dark' ? ' in dark mode' : '' }}"
                            >
                                @if ($mode === 'dark')
                                    <template x-if="! slotValue({{ Js::from($key) }}, 'dark')">
                                        <span class="flex min-w-0 items-center gap-2 text-slate-400">
                                            <span
                                                class="flex h-5 w-5 shrink-0 items-center justify-center rounded border border-dashed border-slate-300 dark:border-slate-600"
                                            >
                                                <x-admin.icon name="minus" class="h-3 w-3" />
                                            </span>
                                            <span class="truncate text-xs">Same as light</span>
                                        </span>
                                    </template>
                                @endif

                                <template x-if="slotValue({{ Js::from($key) }}, {{ Js::from($mode) }})">
                                    <span class="flex min-w-0 items-center gap-2">
                                        <span
                                            class="media-checker relative h-5 w-5 shrink-0 overflow-hidden rounded ring-1 ring-black/10 ring-inset dark:ring-white/15"
                                        >
                                            <span
                                                class="absolute inset-0"
                                                x-bind:style="'background:' + swatch(slotValue({{ Js::from($key) }}, {{ Js::from($mode) }}))"
                                            ></span>
                                        </span>
                                        <span
                                            class="truncate font-mono text-xs text-slate-700 dark:text-slate-200"
                                            x-text="slotValue({{ Js::from($key) }}, {{ Js::from($mode) }})"
                                        ></span>
                                    </span>
                                </template>
                                <x-admin.icon name="chevron-down" class="ml-auto h-3.5 w-3.5 shrink-0 text-slate-400" />
                            </button>
                        @endforeach
                    </li>
                @endforeach
            </ul>
        </x-admin.card>
    @endforeach
</div>
