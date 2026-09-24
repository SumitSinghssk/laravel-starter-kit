@props([
    'name' => null,
    'id' => null,
    'label' => null,
    'value' => null,
    'mode' => 'single',
    'startName' => null,
    'endName' => null,
    'withTime' => false,
    'min' => null,
    'max' => null,
    'placeholder' => null,
    'clearable' => true,
    'description' => null,
    'hint' => null,
    'error' => null,
    'required' => false,
    'disabled' => false,
    'wrapperClass' => '',
])

@php
    use App\Support\FormField;

    $isRange = $mode === 'range';
    $id ??= FormField::id($name ?? $startName) ?: 'date-' . \Illuminate\Support\Str::random(6);
    $message = $isRange ? FormField::error($startName, $error, $errors ?? null) ?? FormField::error($endName, null, $errors ?? null) : FormField::error($name, $error, $errors ?? null);

    if ($isRange) {
        $current = [
            'start' => (string) FormField::old($startName, $value['start'] ?? ''),
            'end' => (string) FormField::old($endName, $value['end'] ?? ''),
        ];
    } else {
        $current = FormField::old($name, $value);
        $current = $current instanceof \DateTimeInterface ? $current->format($withTime ? 'Y-m-d\TH:i' : 'Y-m-d') : (string) $current;
    }

    $placeholder ??= $isRange ? 'Any date' : ($withTime ? 'Pick a date and time' : 'Pick a date');
    $describedBy = $message ? "$id-error" : (filled($hint) ? "$id-hint" : null);
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
        x-data="adminDatePicker({
                    value: @js($current),
                    mode: @js($mode),
                    withTime: @js((bool) $withTime),
                    min: @js((string) $min),
                    max: @js((string) $max),
                    disabled: @js($disabled),
                })"
        x-modelable="value"
        x-on:click.window="outside($event)"
        {{ $attributes->class('relative') }}
    >
        @if ($isRange)
            @if ($startName)
                <input type="hidden" name="{{ $startName }}" value="{{ $current['start'] }}" x-bind:value="value.start" />
            @endif

            @if ($endName)
                <input type="hidden" name="{{ $endName }}" value="{{ $current['end'] }}" x-bind:value="value.end" />
            @endif
        @elseif ($name)
            <input type="hidden" name="{{ $name }}" value="{{ $current }}" x-bind:value="value" />
        @endif

        <button
            type="button"
            id="{{ $id }}"
            x-ref="trigger"
            x-on:click="open ? hide() : show()"
            x-on:keydown.arrow-down.prevent="show()"
            aria-haspopup="dialog"
            aria-expanded="false"
            x-bind:aria-expanded="open.toString()"
            @if ($message) aria-invalid="true" @endif
            @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif
            @disabled($disabled)
            @class([FormField::controlClasses((bool) $message, $disabled), 'flex items-center gap-2 py-2 pr-2.5 pl-3 text-left', 'cursor-pointer' => ! $disabled])
        >
            <x-admin.icon name="calendar" class="h-4 w-4 text-slate-400" />
            <span class="flex-1 truncate" x-text="display || @js($placeholder)" x-bind:class="{ 'text-slate-400': ! display }">
                {{ $placeholder }}
            </span>
            <x-admin.icon name="chevron-down" class="h-4 w-4 text-slate-400" />
        </button>

        @include('admin.partials.controls.date-panel', ['panelLabel' => $label ?: 'Choose a date', 'clearable' => $clearable])
    </div>
</x-admin.form.field>
