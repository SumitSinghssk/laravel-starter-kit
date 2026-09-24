@props([
    'for' => null,
    'required' => false,
    'description' => null,
])

<div {{ $attributes->class('mb-1.5') }}>
    <label @if ($for) for="{{ $for }}" @endif class="block text-[13px] font-medium text-slate-700 dark:text-slate-200">
        {{ $slot }}
        @if ($required)
            <span class="ml-0.5 text-red-500" aria-hidden="true">*</span>
        @endif
    </label>

    @if (filled($description))
        <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">{{ $description }}</p>
    @endif
</div>
