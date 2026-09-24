@props([
    "type" => "submit",
    "variant" => "primary",
    "size" => "md",
    "href" => null,
    "full" => false,
    "loading" => false,
    "iconOnly" => false,
    "icon" => null,
])

@php
    $base = "inline-flex cursor-pointer items-center justify-center gap-1.5 rounded-lg font-semibold whitespace-nowrap transition-all duration-150 select-none focus:outline-none focus-visible:ring-3 active:scale-[0.98] disabled:cursor-not-allowed disabled:opacity-50 disabled:active:scale-100 aria-disabled:pointer-events-none aria-disabled:opacity-50";

    $sizes = $iconOnly ? ["xs" => "h-6 w-6 text-[11px]", "sm" => "h-7.5 w-7.5 text-xs", "md" => "h-9 w-9 text-sm", "lg" => "h-10 w-10 text-sm"][$size] : ["xs" => "h-7 px-2 text-[11px]", "sm" => "h-8 px-2.5 text-xs", "md" => "h-9 px-3.5 text-sm", "lg" => "h-10 px-4.5 text-sm"][$size];

    $variants = [
        "primary" => "bg-blue-600 text-white shadow-xs inset-ring inset-ring-blue-700/40 hover:bg-blue-700 focus-visible:ring-blue-500/30 dark:bg-blue-500 dark:inset-ring-white/10 dark:hover:bg-blue-400",
        "secondary" => "border border-slate-200 bg-white text-slate-700 shadow-xs hover:border-slate-300 hover:bg-slate-50 hover:text-slate-900 focus-visible:ring-blue-500/20 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100 dark:hover:bg-slate-700 dark:hover:text-white",
        "subtle" => "bg-slate-100 text-slate-700 hover:bg-slate-200 focus-visible:ring-blue-500/20 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700",
        "ghost" => "bg-transparent text-slate-600 hover:bg-slate-100 focus-visible:ring-blue-500/20 dark:text-slate-300 dark:hover:bg-slate-800",
        "outline" => "border border-slate-300 bg-transparent text-slate-700 hover:border-slate-400 hover:bg-slate-50 focus-visible:ring-blue-500/20 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800",
        "success" => "bg-emerald-600 text-white shadow-xs hover:bg-emerald-700 focus-visible:ring-emerald-500/30",
        "warning" => "bg-amber-400 text-slate-900 shadow-sm shadow-amber-400/25 hover:bg-amber-500 focus-visible:ring-amber-400/40",
        "danger" => "bg-red-600 text-white shadow-xs hover:bg-red-700 focus-visible:ring-red-500/30",
        "danger-outline" => "border border-red-200 bg-white text-red-600 shadow-xs hover:bg-red-50 focus-visible:ring-red-500/30 dark:border-red-500/30 dark:bg-slate-900 dark:text-red-400 dark:hover:bg-red-500/10",
        "link" => "bg-transparent !p-0 text-blue-600 hover:text-blue-700 hover:underline focus-visible:ring-blue-500/30 dark:text-blue-400",
    ];

    $classes = implode(" ", [$base, $sizes, $variants[$variant] ?? $variants["primary"], $full ? "w-full" : ""]);
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->class($classes) }}>
        @if ($icon)
            <x-admin.icon :name="$icon" class="h-4 w-4" />
        @endif

        {{ $leftIcon ?? "" }}
        @if ($iconOnly)
            {{ $slot }}
        @else
            <span class="truncate">{{ $slot }}</span>
        @endif
        {{ $rightIcon ?? "" }}
    </a>
@else
    <button
        type="{{ $type }}"
        x-data
        @if ($loading)
            disabled
            aria-busy="true"
        @elseif (! $attributes->hasAny(["x-bind:disabled", ":disabled", "disabled"]))
            x-bind:disabled="typeof submitting !== 'undefined' ? submitting : false"
            x-bind:aria-busy="(typeof submitting !== 'undefined' && submitting).toString()"
        @endif
        {{ $attributes->class($classes) }}
    >
        @if ($loading)
            <x-icons.spinner class="h-4 w-4 animate-spin" aria-hidden="true" />
        @else
            <x-icons.spinner x-cloak x-show="typeof submitting !== 'undefined' && submitting" class="h-4 w-4 animate-spin" aria-hidden="true" />
            @if ($icon)
                <x-admin.icon :name="$icon" class="h-4 w-4" />
            @endif

            @if (! $iconOnly)
                {{ $leftIcon ?? "" }}
            @endif
        @endif

        @if ($iconOnly)
            <span x-show="typeof submitting === 'undefined' || ! submitting">{{ $slot }}</span>
        @else
            <span class="truncate">{{ $slot }}</span>
        @endif

        {{ $rightIcon ?? "" }}
    </button>
@endif
