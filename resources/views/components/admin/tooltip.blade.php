@props(['text' => '', 'position' => 'top'])

@php
    $positionClasses = match ($position) {
        'bottom' => 'top-full left-1/2 mt-2 -translate-x-1/2',
        'left' => 'top-1/2 right-full mr-2 -translate-y-1/2',
        'right' => 'top-1/2 left-full ml-2 -translate-y-1/2',
        default => 'bottom-full left-1/2 mb-2 -translate-x-1/2',
    };

    $animation = 'opacity-0 group-hover/tooltip:opacity-100 group-hover/tooltip:delay-300 group-has-[:focus-visible]/tooltip:opacity-100';
@endphp

<div class="group/tooltip relative inline-flex">
    {{ $slot }}
    @if ($text)
        <div
            class="{{ $positionClasses }} {{ $animation }} pointer-events-none absolute z-[200] transition-opacity duration-150"
            role="presentation"
        >
            <div class="rounded-md bg-slate-900 px-2 py-1 text-[11px] font-medium whitespace-nowrap text-white shadow-lg dark:bg-slate-700">
                {{ $text }}
            </div>
        </div>
    @endif
</div>
