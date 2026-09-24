@props([
    'title' => '',
    'subtitle' => '',
    'text' => '',
    'icon' => null,
    'padded' => true,
    'bodyClass' => '',
])

@php
    $hasTitle = isset($title) && (is_object($title) ? (string) $title !== '' : $title !== '');
    $hasHeader = $hasTitle || $subtitle || $text || isset($actions) || isset($extra);
@endphp

<section
    {{ $attributes->merge(['class' => 'rounded-xl border border-slate-200/80 bg-white shadow-xs dark:border-slate-800 dark:bg-slate-900']) }}
>
    @if ($hasHeader)
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-4 py-3.5 sm:px-5 dark:border-slate-800">
            <div class="flex min-w-0 items-center gap-3">
                @if ($icon)
                    <span
                        class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg border border-slate-200 bg-slate-50 text-slate-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300"
                    >
                        <x-admin.icon :name="$icon" class="h-4 w-4" />
                    </span>
                @endif

                <div class="min-w-0">
                    @if ($hasTitle)
                        @if (is_object($title))
                            {{ $title }}
                        @else
                            <h2 class="text-sm font-semibold text-slate-900 dark:text-white">{{ $title }}</h2>
                        @endif
                    @endif

                    @if ($subtitle || $text)
                        <div class="mt-0.5 flex flex-wrap items-center gap-2">
                            @if ($subtitle)
                                <span
                                    class="rounded-full bg-blue-50 px-2 py-px text-[11px] font-semibold text-blue-700 dark:bg-blue-500/10 dark:text-blue-300"
                                >
                                    {{ $subtitle }}
                                </span>
                            @endif

                            @if ($text)
                                <p class="text-xs text-slate-500 dark:text-slate-400">{{ $text }}</p>
                            @endif
                        </div>
                    @endif
                </div>
            </div>

            @if (isset($actions) || isset($extra))
                <div class="flex flex-wrap items-center gap-2">
                    @isset($extra)
                        {{ $extra }}
                    @endisset

                    @isset($actions)
                        {{ $actions }}
                    @endisset
                </div>
            @endif
        </div>
    @endif

    <div @class(['relative', 'p-4 sm:p-5' => $padded, $bodyClass])>
        {{ $slot }}
    </div>
</section>
