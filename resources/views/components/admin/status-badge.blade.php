@props([
    'status' => null,
    'tone' => null,
    'label' => null,
    'dot' => true,
])

@php
    $statusValue = $status instanceof \BackedEnum ? $status->value : $status;

    $known = [
        'active' => ['success', 'Active'],
        'inactive' => ['neutral', 'Inactive'],
        'new' => ['info', 'New'],
        'seen' => ['neutral', 'Seen'],
        'pending' => ['warning', 'Pending'],
        'closed' => ['success', 'Closed'],
    ];

    [$knownTone, $knownLabel] = $known[$statusValue] ?? ['neutral', $statusValue ? ucfirst((string) $statusValue) : ''];
    $tone ??= $knownTone;
    $label ??= $knownLabel;

    $tones = [
        'success' => ['bg-emerald-50 text-emerald-700 ring-emerald-600/15 dark:bg-emerald-500/10 dark:text-emerald-300 dark:ring-emerald-400/20', 'bg-emerald-500'],
        'neutral' => ['bg-slate-50 text-slate-600 ring-slate-500/15 dark:bg-slate-800 dark:text-slate-300 dark:ring-slate-600/40', 'bg-slate-400'],
        'info' => ['bg-blue-50 text-blue-700 ring-blue-600/15 dark:bg-blue-500/10 dark:text-blue-300 dark:ring-blue-400/20', 'bg-blue-500'],
        'brand' => ['bg-violet-50 text-violet-700 ring-violet-600/15 dark:bg-violet-500/10 dark:text-violet-300 dark:ring-violet-400/20', 'bg-violet-500'],
        'warning' => ['bg-amber-50 text-amber-700 ring-amber-600/20 dark:bg-amber-500/10 dark:text-amber-300 dark:ring-amber-400/20', 'bg-amber-500'],
        'danger' => ['bg-red-50 text-red-700 ring-red-600/15 dark:bg-red-500/10 dark:text-red-300 dark:ring-red-400/20', 'bg-red-500'],
    ];

    [$classes, $dotClass] = $tones[$tone] ?? $tones['neutral'];
@endphp

<span
    {{ $attributes->class("$classes inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-xs font-medium whitespace-nowrap ring-1 ring-inset") }}
>
    @if ($dot)
        <span class="{{ $dotClass }} h-1.5 w-1.5 rounded-full"></span>
    @endif

    {{ $label }}
</span>
