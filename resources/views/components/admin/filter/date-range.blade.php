@props([
    'label' => 'Date',
    'startName' => 'date_from',
    'endName' => 'date_to',
    'icon' => 'calendar',
])

@php
    $start = (string) request($startName, '');
    $end = (string) request($endName, '');
    $isSet = $start !== '' || $end !== '';
    $fmt = fn ($d) => rescue(fn () => \Illuminate\Support\Carbon::parse($d)->format('j M Y'), $d, false);
    $display = $isSet ? ($start ? $fmt($start) : '…') . ' – ' . ($end ? $fmt($end) : '…') : '';
    $id = 'filter-' . \App\Support\FormField::id($startName);
@endphp

<div
    data-filter
    x-data="adminDatePicker({ value: @js(['start' => $start, 'end' => $end]), mode: 'range' })"
    x-on:click.window="outside($event)"
    {{ $attributes->class('relative flex items-center') }}
>
    <input type="hidden" name="{{ $startName }}" value="{{ $start }}" x-bind:value="value.start" />
    <input type="hidden" name="{{ $endName }}" value="{{ $end }}" x-bind:value="value.end" />

    <button
        type="button"
        id="{{ $id }}"
        x-ref="trigger"
        x-on:click="open ? hide() : show()"
        x-on:keydown.arrow-down.prevent="show()"
        aria-haspopup="dialog"
        aria-expanded="false"
        x-bind:aria-expanded="open.toString()"
        aria-label="{{ $label }}{{ $isSet ? ': ' . $display : '' }}"
        @class([
            'inline-flex h-8 cursor-pointer items-center gap-1.5 border text-xs font-medium whitespace-nowrap transition focus:outline-none focus-visible:ring-3 focus-visible:ring-blue-500/30',
            'rounded-lg border-dashed border-slate-300 px-2.5 text-slate-600 hover:border-slate-400 hover:bg-slate-50 hover:text-slate-900 dark:border-slate-600 dark:text-slate-300 dark:hover:border-slate-500 dark:hover:bg-slate-800 dark:hover:text-white' => ! $isSet,
            'rounded-l-lg border-r-0 border-slate-200 bg-white pr-2 pl-2.5 text-slate-700 shadow-xs hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700' => $isSet,
        ])
        x-bind:class="open && 'ring-3 ring-blue-500/15'"
    >
        @if ($isSet)
            <x-admin.icon :name="$icon" class="h-3.5 w-3.5 text-slate-400" />
            <span class="text-slate-500 dark:text-slate-400">{{ $label }}</span>
            <span class="h-3.5 w-px bg-slate-200 dark:bg-slate-600"></span>
            <span class="tabular font-semibold text-slate-900 dark:text-white">{{ $display }}</span>
        @else
            <x-admin.icon name="plus" class="h-3.5 w-3.5 text-slate-400" />
            <span>{{ $label }}</span>
        @endif
        <x-admin.icon name="chevron-down" class="h-3.5 w-3.5 text-slate-400" />
    </button>

    @if ($isSet)
        <button
            type="button"
            x-on:click="clear()"
            aria-label="Clear {{ $label }} filter"
            title="Clear"
            class="inline-flex h-8 w-7 cursor-pointer items-center justify-center rounded-r-lg border border-slate-200 bg-white text-slate-400 shadow-xs transition hover:bg-slate-50 hover:text-slate-700 dark:border-slate-700 dark:bg-slate-800 dark:hover:bg-slate-700 dark:hover:text-white"
        >
            <x-admin.icon name="x" class="h-3.5 w-3.5" />
        </button>
    @endif

    @include('admin.partials.controls.date-panel', ['panelLabel' => $label, 'clearable' => true])
</div>
