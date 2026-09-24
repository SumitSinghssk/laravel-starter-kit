@props(['src' => null, 'icon' => 'image', 'alt' => ''])

<span
    {{ $attributes->class('relative flex shrink-0 items-center justify-center overflow-hidden rounded-md bg-slate-100 text-slate-400 ring-1 ring-slate-200/80 dark:bg-slate-800 dark:text-slate-500 dark:ring-slate-700') }}
>
    <x-admin.icon :name="$icon" class="h-4 w-4" />
    @if ($src)
        <img src="{{ $src }}" alt="{{ $alt }}" loading="lazy" onerror="this.remove()" class="absolute inset-0 h-full w-full object-cover" />
    @endif
</span>
