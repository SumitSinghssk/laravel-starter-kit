@props([
    'title',
    'description' => null,
    'icon' => null,
    'count' => null,
    'back' => null,
])

<div {{ $attributes->class('mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between') }}>
    <div class="flex min-w-0 items-center gap-3.5">
        @if ($back)
            <a
                href="{{ $back }}"
                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-500 shadow-xs transition hover:border-slate-300 hover:text-slate-900 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-400 dark:hover:text-white"
                aria-label="Back"
                title="Back"
            >
                <x-admin.icon name="arrow-left" class="h-4.5 w-4.5" />
            </a>
        @elseif ($icon)
            <span
                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-600 shadow-xs dark:border-slate-800 dark:bg-slate-900 dark:text-slate-300"
            >
                <x-admin.icon :name="$icon" class="h-5 w-5" />
            </span>
        @endif

        <div class="min-w-0">
            <div class="flex items-center gap-2">
                <h1 class="truncate text-xl font-semibold tracking-tight text-slate-900 dark:text-white">{{ $title }}</h1>
                @if (! is_null($count))
                    <span
                        class="tabular rounded-full border border-slate-200 bg-white px-2 py-px text-xs font-medium text-slate-600 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300"
                    >
                        {{ number_format($count) }}
                    </span>
                @endif
            </div>
            @if ($description)
                <p class="mt-0.5 line-clamp-2 text-sm text-slate-500 sm:line-clamp-1 dark:text-slate-400">{{ $description }}</p>
            @endif
        </div>
    </div>

    @isset($actions)
        <div class="flex shrink-0 flex-wrap items-center gap-2">
            {{ $actions }}
        </div>
    @endisset
</div>
