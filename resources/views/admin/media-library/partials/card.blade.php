@php
    $dimensions = $file->width && $file->height ? "{$file->width} × {$file->height}" : null;
    $sizeLabel = \Illuminate\Support\Number::fileSize($file->size, 1);
@endphp

<li
    x-data="{
        thumb: @js($file->thumb_url),
        size: @js($sizeLabel),
        dims: @js($dimensions),
        gone: false,
    }"
    x-show="! gone"
    x-on:media-updated.window="
        if ($event.detail.id === {{ $file->id }}) {
            thumb = $event.detail.thumb
            size = $event.detail.size
            dims = $event.detail.dimensions
        }
    "
    x-on:media-deleted.window="if ($event.detail.id === {{ $file->id }}) gone = true"
>
    <button
        type="button"
        x-on:click="open(@js(route('admin.media-library.show', $file)))"
        class="group block w-full cursor-pointer overflow-hidden rounded-xl border border-slate-200 bg-white text-left transition hover:border-slate-300 hover:shadow-md focus:outline-none focus-visible:ring-3 focus-visible:ring-blue-500/40 dark:border-slate-800 dark:bg-slate-900 dark:hover:border-slate-700"
        aria-label="Details for {{ $file->filename }}"
    >
        <span class="media-checker relative block aspect-square overflow-hidden">
            <img
                src="{{ $file->thumb_url }}"
                x-bind:src="thumb"
                alt=""
                loading="lazy"
                decoding="async"
                class="h-full w-full object-contain p-1.5 transition duration-300 group-hover:scale-[1.03]"
                x-on:error="$el.style.visibility = 'hidden'"
            />

            <span class="pointer-events-none absolute top-1.5 left-1.5 flex flex-wrap gap-1">
                @if ($file->location === \App\Models\MediaFile::PUBLIC)
                    <span
                        class="rounded bg-amber-400 px-1.5 py-px text-[10px] font-semibold text-amber-950"
                        title="In the public folder (part of the site's code)"
                    >
                        Public
                    </span>
                @endif

                @if ($file->isLarge())
                    <span class="rounded bg-slate-950/70 px-1.5 py-px text-[10px] font-semibold text-white" title="Large file: slows pages down">
                        Large
                    </span>
                @endif
            </span>

            <span
                class="pointer-events-none absolute right-1.5 bottom-1.5 rounded bg-slate-950/70 px-1.5 py-px text-[10px] font-semibold text-white uppercase"
            >
                {{ $file->extension }}
            </span>
        </span>

        <span class="block border-t border-slate-100 px-2.5 py-2 dark:border-slate-800">
            <span class="block truncate text-xs font-medium text-slate-800 dark:text-slate-100" title="{{ $file->path }}">
                {{ $file->filename }}
            </span>
            <span class="tabular mt-0.5 block truncate text-[11px] text-slate-500 dark:text-slate-400">
                <span x-text="[dims, size].filter(Boolean).join(' · ')">{{ collect([$dimensions, $sizeLabel])->filter()->implode(' · ') }}</span>
            </span>
            <span class="mt-1.5 flex items-center gap-1 text-[11px]">
                @if ($file->usage_count)
                    <span class="inline-flex items-center gap-1 font-medium text-emerald-700 dark:text-emerald-400">
                        <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                        Used in {{ $file->usage_count }} {{ Str::plural('place', $file->usage_count) }}
                    </span>
                @else
                    <span class="inline-flex items-center gap-1 text-slate-400">
                        <span class="h-1.5 w-1.5 rounded-full bg-slate-300 dark:bg-slate-600"></span>
                        Not used
                    </span>
                @endif
            </span>
        </span>
    </button>
</li>
