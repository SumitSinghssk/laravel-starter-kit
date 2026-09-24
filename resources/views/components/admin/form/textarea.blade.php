@props([
    'name' => null,
    'id' => null,
    'label' => null,
    'value' => null,
    'rows' => 3,
    'description' => null,
    'hint' => null,
    'error' => null,
    'required' => false,
    'disabled' => false,
    'editor' => false,
    'wrapperClass' => '',
])

@php
    use App\Support\FormField;

    $id ??= FormField::id($name);
    $message = FormField::error($name, $error, $errors ?? null);
    $content = is_null($value) ? trim((string) $slot) : e(FormField::old($name, $value));
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
    <textarea
        id="{{ $id }}"
        @if ($name) name="{{ $name }}" @endif
        rows="{{ $rows }}"
        @required($required)
        @disabled($disabled)
        @if ($message) aria-invalid="true" @endif
        @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif
        {{ $attributes->class([FormField::controlClasses((bool) $message, $disabled), 'resize-y px-3 py-2', 'tinymce' => $editor]) }}
    >
{!! $content !!}</textarea
    >
</x-admin.form.field>
