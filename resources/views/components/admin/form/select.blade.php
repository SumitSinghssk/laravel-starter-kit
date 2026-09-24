@props([
    'name' => null,
    'id' => null,
    'label' => null,
    'options' => [],
    'value' => null,
    'placeholder' => 'Select…',
    'searchable' => null,
    'emptyMessage' => 'No options',
    'description' => null,
    'hint' => null,
    'error' => null,
    'required' => false,
    'disabled' => false,
    'menuWidth' => 0,
    'icon' => null,
    'wrapperClass' => '',
])

@php
    use App\Support\FormField;

    $dynamicId = $id === false;
    $id = $dynamicId ? null : $id ?? (FormField::id($name) ?: null);
    $message = FormField::error($name, $error, $errors ?? null);
    $list = FormField::options($options);
    $current = FormField::old($name, $value);
    $current = $current instanceof \BackedEnum ? $current->value : $current;
    $current = is_null($current) ? '' : (string) $current;
    $searchable ??= count($list) > 8;
    $selectedLabel = collect($list)->firstWhere('value', $current)['label'] ?? null;
    $describedBy = ! $id ? null : ($message ? "$id-error" : (filled($hint) ? "$id-hint" : null));
    $ariaLabel = $attributes->get('aria-label') ?? ($dynamicId && filled($label) ? $label : null);
    $attributes = $attributes->except('aria-label');
@endphp

<x-admin.form.field
    :label="$label"
    :for="$id"
    :required="$required"
    :description="$description"
    :hint="$hint"
    :error="$message"
    :class="$wrapperClass"
>
    <div
        x-data="adminSelect({
                    id: @js($id),
                    options: @js($list),
                    value: @js($current),
                    searchable: @js($searchable),
                    disabled: @js($disabled),
                    minWidth: @js((int) $menuWidth),
                })"
        x-modelable="value"
        x-id="['admin-select']"
        x-on:click.window="outside($event)"
        {{ $attributes->class('relative') }}
    >
        @if ($name)
            <input type="hidden" name="{{ $name }}" value="{{ $current }}" x-bind:value="value" />
        @endif

        <button
            type="button"
            @if ($id) id="{{ $id }}" @else x-bind:id="uid" @endif
            @if ($ariaLabel) aria-label="{{ $ariaLabel }}" @endif
            x-ref="trigger"
            x-on:click="toggle()"
            x-on:keydown="key($event)"
            aria-haspopup="listbox"
            aria-expanded="false"
            x-bind:aria-expanded="open.toString()"
            x-bind:aria-controls="uid + '-listbox'"
            x-bind:aria-activedescendant="open && ! searchable && filtered.length ? uid + '-option-' + active : null"
            @if ($message) aria-invalid="true" @endif
            @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif
            @if ($required) aria-required="true" @endif
            @disabled($disabled)
            @class([FormField::controlClasses((bool) $message, $disabled), 'flex items-center justify-between gap-2 py-2 pr-2.5 pl-3 text-left', 'cursor-pointer' => ! $disabled])
        >
            @if ($icon)
                <x-admin.icon :name="$icon" class="h-4 w-4 text-slate-400" />
            @endif

            <span x-show="selected?.dot" x-bind:class="selected?.dot" class="h-2 w-2 shrink-0 rounded-full"></span>
            <span
                x-text="selected ? selected.label : @js($placeholder)"
                x-bind:class="{ 'text-slate-400': ! selected }"
                @class(['flex-1 truncate', 'text-slate-400' => is_null($selectedLabel)])
            >
                {{ $selectedLabel ?? $placeholder }}
            </span>
            <x-admin.icon name="chevron-down" class="h-4 w-4 text-slate-400 transition-transform" x-bind:class="open && 'rotate-180'" />
        </button>

        @include('admin.partials.controls.select-panel', ['listLabel' => $ariaLabel ?? $label, 'emptyMessage' => $emptyMessage])
    </div>
</x-admin.form.field>
