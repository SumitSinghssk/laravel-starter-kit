@props([
    'name' => null,
    'id' => null,
    'label' => null,
    'type' => 'text',
    'value' => null,
    'description' => null,
    'hint' => null,
    'error' => null,
    'required' => false,
    'disabled' => false,
    'wrapperClass' => '',
])

@php
    use App\Support\FormField;

    $id = $id === false ? null : $id ?? FormField::id($name);
    $message = FormField::error($name, $error, $errors ?? null);
    $isPassword = $type === 'password';
    $current = in_array($type, ['password', 'file'], true) ? null : FormField::old($name, $value);
    $hasLeft = isset($leftIcon);
    $hasRight = isset($rightIcon) || ($isPassword && ! $disabled);
    $describedBy = ! $id ? null : ($message ? "$id-error" : (filled($hint) ? "$id-hint" : null));
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
    <div class="relative" @if ($isPassword) x-data="{ show: false }" @endif>
        @if ($hasLeft)
            <span
                class="{{ $disabled ? 'text-slate-300 dark:text-slate-600' : 'text-slate-400' }} pointer-events-none absolute inset-y-0 left-3 z-10 flex items-center"
            >
                {{ $leftIcon }}
            </span>
        @endif

        <input
            @if ($id) id="{{ $id }}" @endif
            @if ($name) name="{{ $name }}" @endif
            @if ($isPassword) type="password" x-bind:type="show ? 'text' : 'password'" @else type="{{ $type }}" @endif
            @if (! is_null($current)) value="{{ $current }}" @endif
            @required($required)
            @disabled($disabled)
            @if ($message) aria-invalid="true" @endif
            @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif
            {{ $attributes->class([FormField::controlClasses((bool) $message, $disabled), 'py-2', $hasLeft ? 'pl-9' : 'pl-3', $hasRight ? 'pr-9' : 'pr-3']) }}
        />

        @if ($isPassword && ! $disabled)
            <button
                type="button"
                x-on:click="show = ! show"
                x-bind:aria-label="show ? 'Hide password' : 'Show password'"
                x-bind:aria-pressed="show.toString()"
                class="absolute inset-y-0 right-3 z-20 flex cursor-pointer items-center text-slate-400 transition hover:text-blue-600"
            >
                <x-admin.icon name="eye" x-show="! show" class="h-4 w-4" />
                <x-admin.icon name="eye-off" x-show="show" x-cloak class="h-4 w-4" />
            </button>
        @elseif (isset($rightIcon))
            <span class="pointer-events-none absolute inset-y-0 right-3 z-10 flex items-center text-slate-400">
                {{ $rightIcon }}
            </span>
        @endif
    </div>
</x-admin.form.field>
