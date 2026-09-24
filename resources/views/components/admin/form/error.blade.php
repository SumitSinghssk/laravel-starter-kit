@props([
    'for' => null,
    'message' => null,
    'id' => null,
])

@php
    $message ??= \App\Support\FormField::error($for, null, $errors ?? null);
    $id ??= $for ? \App\Support\FormField::id($for) . '-error' : null;
@endphp

@if ($message)
    <p {{ $attributes->merge(['id' => $id])->class('mt-1.5 flex items-start gap-1.5 text-xs font-medium text-red-600 dark:text-red-400') }}>
        <x-admin.icon name="alert-circle" class="mt-px h-3.5 w-3.5" />
        <span>{{ $message }}</span>
    </p>
@endif
