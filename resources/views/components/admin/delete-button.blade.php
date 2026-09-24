@props([
    'route',
    'id' => null,
    'modalName' => null,
    'size' => 'lg',
    'title' => 'Delete this item?',
    'message' => 'It will be permanently removed. This action cannot be undone.',
    'buttonClass' => '',
])

@php
    $name = $modalName ?? 'delete-modal-' . ($id ?? Str::random(8));
    $isSmall = $size === 'sm';

    $defaultClass = $isSmall
        ? 'inline-flex h-7.5 w-7.5 cursor-pointer items-center justify-center rounded-lg text-slate-400 transition-colors hover:bg-red-50 hover:text-red-600 focus:outline-none focus-visible:ring-3 focus-visible:ring-red-500/30 dark:hover:bg-red-500/10 dark:hover:text-red-400'
        : 'inline-flex h-9 cursor-pointer items-center gap-1.5 rounded-lg border border-red-200 bg-white px-3.5 text-sm font-semibold text-red-600 shadow-xs transition-colors hover:bg-red-50 focus:outline-none focus-visible:ring-3 focus-visible:ring-red-500/30 dark:border-red-500/30 dark:bg-slate-900 dark:text-red-400 dark:hover:bg-red-500/10';
@endphp

<div class="inline-block">
    <x-admin.tooltip text="Delete">
        <button
            type="button"
            x-on:click="$dispatch('open-modal', '{{ $name }}')"
            @if ($isSmall) aria-label="Delete" @endif
            {{ $attributes->merge(['class' => $buttonClass ?: $defaultClass]) }}
        >
            <x-admin.icon name="trash" class="h-4 w-4" />
            @if (! $isSmall)
                <span>Delete</span>
            @endif
        </button>
    </x-admin.tooltip>

    <x-modal name="{{ $name }}" :show="false" maxWidth="md">
        <div class="p-6 text-left">
            <div class="flex items-start gap-4">
                <span
                    class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-red-50 text-red-600 ring-8 ring-red-50/60 dark:bg-red-500/10 dark:text-red-400 dark:ring-red-500/5"
                >
                    <x-admin.icon name="trash" class="h-5 w-5" />
                </span>
                <div class="min-w-0 pt-0.5">
                    <h2 class="text-base font-semibold text-slate-900 dark:text-white">{{ $title }}</h2>
                    <p class="mt-1.5 text-sm leading-relaxed text-slate-500 dark:text-slate-400">{{ $message }}</p>
                </div>
            </div>

            <div class="mt-6 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                <button
                    type="button"
                    x-on:click="$dispatch('close-modal', '{{ $name }}')"
                    class="cursor-pointer rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-xs transition hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700"
                >
                    Cancel
                </button>

                <form action="{{ $route }}" method="POST" x-data="{ busy: false }" x-on:submit="busy = true">
                    @csrf
                    @method('DELETE')
                    <button
                        type="submit"
                        x-bind:disabled="busy"
                        class="inline-flex w-full cursor-pointer items-center justify-center gap-2 rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white shadow-xs transition hover:bg-red-700 disabled:cursor-wait disabled:opacity-60 sm:w-auto"
                    >
                        <x-icons.spinner x-show="busy" x-cloak class="h-4 w-4 animate-spin" />
                        Delete permanently
                    </button>
                </form>
            </div>
        </div>
    </x-modal>
</div>
