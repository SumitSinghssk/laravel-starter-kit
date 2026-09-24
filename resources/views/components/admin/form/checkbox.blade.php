@props([
    'name' => null,
    'id' => null,
    'label' => null,
    'value' => '1',
    'checked' => false,
    'uncheckedValue' => null,
    'hint' => null,
    'error' => null,
    'disabled' => false,
])

@php
    use App\Support\FormField;

    $isList = str_ends_with((string) $name, '[]');
    $id ??= FormField::id($name) . ($isList ? '_' . \Illuminate\Support\Str::slug((string) $value, '_') : '');
    $message = FormField::error($name, $error, $errors ?? null);

    if ($name && session()->hasOldInput()) {
        $old = old(FormField::key($name));
        $isChecked = is_array($old) ? in_array((string) $value, array_map('strval', $old), true) : (string) $old === (string) $value;
    } else {
        $isChecked = (bool) $checked;
    }
@endphp

<div {{ $attributes->only('class') }}>
    <label
        for="{{ $id }}"
        @class(['inline-flex items-start gap-2.5', 'cursor-not-allowed opacity-60' => $disabled, 'cursor-pointer' => ! $disabled])
    >
        @if (! is_null($uncheckedValue) && $name)
            <input type="hidden" name="{{ $name }}" value="{{ $uncheckedValue }}" @disabled($disabled) />
        @endif

        <span class="relative mt-0.5 inline-flex shrink-0">
            <input
                type="checkbox"
                id="{{ $id }}"
                @if ($name) name="{{ $name }}" @endif
                value="{{ $value }}"
                @checked($isChecked)
                @disabled($disabled)
                @if ($message) aria-invalid="true" aria-describedby="{{ $id }}-error" @elseif (filled($hint)) aria-describedby="{{ $id }}-hint" @endif
                {{ $attributes->except('class')->class(['peer h-4 w-4 cursor-pointer appearance-none rounded border bg-white transition-colors checked:border-blue-600 checked:bg-blue-600 focus:outline-none focus-visible:ring-3 focus-visible:ring-blue-500/25 disabled:cursor-not-allowed dark:bg-slate-800 dark:checked:border-blue-500 dark:checked:bg-blue-500', $message ? 'border-red-400' : 'border-slate-300 dark:border-slate-600']) }}
            />
            <x-admin.icon
                name="check"
                stroke="3"
                class="pointer-events-none absolute inset-0 m-auto h-3 w-3 text-white opacity-0 peer-checked:opacity-100"
            />
        </span>

        @if (filled($label) || filled($hint))
            <span class="flex flex-col leading-snug select-none">
                @if (filled($label))
                    <span class="text-sm text-slate-700 dark:text-slate-200">{{ $label }}</span>
                @endif

                @if (filled($hint))
                    <span id="{{ $id }}-hint" class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">{{ $hint }}</span>
                @endif
            </span>
        @endif
    </label>

    <x-admin.form.error :id="$id . '-error'" :message="$message" />
</div>
