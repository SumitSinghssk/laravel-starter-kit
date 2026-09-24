@props([
    'name' => null,
    'id' => null,
    'label' => null,
    'hint' => null,
    'checked' => false,
    'onValue' => '1',
    'offValue' => '0',
    'size' => 'md',
    'error' => null,
    'disabled' => false,
    'labelPosition' => 'right',
])

@php
    use App\Support\FormField;

    $id ??= FormField::id($name) ?: 'toggle-' . \Illuminate\Support\Str::random(6);
    $message = FormField::error($name, $error, $errors ?? null);
    $isOn = $name && session()->hasOldInput() ? (string) old(FormField::key($name)) === (string) $onValue : (bool) $checked;

    [$track, $knob, $shift] = $size === 'sm' ? ['h-4 w-7', 'h-3 w-3', 'translate-x-3'] : ['h-6 w-11', 'h-5 w-5', 'translate-x-5'];
@endphp

<div
    x-data="{ on: @js($isOn) }"
    x-modelable="on"
    {{ $attributes->class(['flex items-start gap-3', 'flex-row-reverse justify-between' => $labelPosition === 'left']) }}
>
    @if ($name)
        <input
            type="hidden"
            name="{{ $name }}"
            value="{{ $isOn ? $onValue : $offValue }}"
            x-bind:value="on ? @js((string) $onValue) : @js((string) $offValue)"
        />
    @endif

    <button
        type="button"
        id="{{ $id }}"
        x-ref="switch"
        role="switch"
        aria-checked="{{ $isOn ? 'true' : 'false' }}"
        x-bind:aria-checked="on.toString()"
        @if (filled($label)) aria-labelledby="{{ $id }}-label" @endif
        @if ($message) aria-describedby="{{ $id }}-error" @elseif (filled($hint)) aria-describedby="{{ $id }}-hint" @endif
        @disabled($disabled)
        x-on:click="
            on = ! on
            $nextTick(() => $el.dispatchEvent(new Event('change', { bubbles: true })))
        "
        x-bind:class="{
            'bg-blue-600 dark:bg-blue-500': on,
            'bg-slate-300 dark:bg-slate-600': ! on,
        }"
        class="{{ $track }} {{ $isOn ? 'bg-blue-600' : 'bg-slate-300' }} relative mt-px inline-flex shrink-0 cursor-pointer rounded-full transition-colors focus:outline-none focus-visible:ring-3 focus-visible:ring-blue-500/30 disabled:cursor-not-allowed disabled:opacity-60"
    >
        <span
            x-bind:class="{ @js($shift): on, 'translate-x-0': ! on }"
            class="{{ $knob }} {{ $isOn ? $shift : 'translate-x-0' }} absolute top-0.5 left-0.5 rounded-full bg-white shadow-sm transition-transform"
        ></span>
    </button>

    <div class="flex min-w-0 flex-col leading-snug">
        @if (filled($label))
            <span
                id="{{ $id }}-label"
                @unless ($disabled) x-on:click="$refs.switch.click()" @endunless
                @class(['text-sm font-medium text-slate-700 select-none dark:text-slate-200', 'cursor-pointer' => ! $disabled])
            >
                {{ $label }}
            </span>
        @endif

        @if (filled($hint))
            <span id="{{ $id }}-hint" class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">{{ $hint }}</span>
        @endif

        <x-admin.form.error :id="$id . '-error'" :message="$message" />
    </div>
</div>
