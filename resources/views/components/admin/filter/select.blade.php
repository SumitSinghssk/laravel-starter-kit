@props([
    'name',
    'label',
    'icon' => 'filter',
    'options' => [],
    'value' => null,
    'searchable' => null,
    'menuWidth' => 220,
])

@php
    use App\Support\FormField;

    $list = collect(FormField::options($options))
        ->reject(fn ($option) => $option['value'] === '')
        ->values()
        ->all();
    $current = (string) ($value ?? request($name, ''));
    $selected = collect($list)->firstWhere('value', $current);
    $searchable ??= count($list) > 8;
    $id = 'filter-' . FormField::id($name);
@endphp

<div
    data-filter
    x-data="adminSelect({
                id: @js($id),
                options: @js($list),
                value: @js($current),
                searchable: @js($searchable),
                minWidth: @js((int) $menuWidth),
            })"
    x-on:click.window="outside($event)"
    {{ $attributes->class('relative flex items-center') }}
>
    <input type="hidden" name="{{ $name }}" value="{{ $current }}" x-bind:value="value" />

    <button
        type="button"
        id="{{ $id }}"
        x-ref="trigger"
        x-on:click="toggle()"
        x-on:keydown="key($event)"
        aria-haspopup="listbox"
        aria-expanded="false"
        x-bind:aria-expanded="open.toString()"
        x-bind:aria-controls="uid + '-listbox'"
        x-bind:aria-activedescendant="open && ! searchable && filtered.length ? uid + '-option-' + active : null"
        aria-label="{{ $label }}{{ $selected ? ': ' . $selected['label'] : '' }}"
        @class([
            'inline-flex h-8 cursor-pointer items-center gap-1.5 border text-xs font-medium whitespace-nowrap transition focus:outline-none focus-visible:ring-3 focus-visible:ring-blue-500/30',
            'rounded-lg border-dashed border-slate-300 px-2.5 text-slate-600 hover:border-slate-400 hover:bg-slate-50 hover:text-slate-900 dark:border-slate-600 dark:text-slate-300 dark:hover:border-slate-500 dark:hover:bg-slate-800 dark:hover:text-white' => ! $selected,
            'rounded-l-lg border-r-0 border-slate-200 bg-white pr-2 pl-2.5 text-slate-700 shadow-xs hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700' => $selected,
        ])
        x-bind:class="open && 'ring-3 ring-blue-500/15'"
    >
        @if ($selected)
            <x-admin.icon :name="$icon" class="h-3.5 w-3.5 text-slate-400" />
            <span class="text-slate-500 dark:text-slate-400">{{ $label }}</span>
            <span class="h-3.5 w-px bg-slate-200 dark:bg-slate-600"></span>
            @if (! empty($selected['dot']))
                <span class="{{ $selected['dot'] }} h-2 w-2 rounded-full"></span>
            @endif

            <span class="max-w-40 truncate font-semibold text-slate-900 dark:text-white">{{ $selected['label'] }}</span>
        @else
            <x-admin.icon name="plus" class="h-3.5 w-3.5 text-slate-400" />
            <span>{{ $label }}</span>
        @endif
        <x-admin.icon name="chevron-down" class="h-3.5 w-3.5 text-slate-400" />
    </button>

    @if ($selected)
        <button
            type="button"
            x-on:click="
                value = ''
                changed()
            "
            aria-label="Clear {{ $label }} filter"
            title="Clear"
            class="inline-flex h-8 w-7 cursor-pointer items-center justify-center rounded-r-lg border border-slate-200 bg-white text-slate-400 shadow-xs transition hover:bg-slate-50 hover:text-slate-700 dark:border-slate-700 dark:bg-slate-800 dark:hover:bg-slate-700 dark:hover:text-white"
        >
            <x-admin.icon name="x" class="h-3.5 w-3.5" />
        </button>
    @endif

    @include('admin.partials.controls.select-panel', ['listLabel' => $label, 'emptyMessage' => 'No options'])
</div>
