@props([
    'action',
    'method' => 'POST',
    'title' => 'Are you sure?',
    'message' => null,
    'confirm' => 'Confirm',
    'tone' => 'danger',
    'icon' => null,
    'size' => 'sm',
    'variant' => 'secondary',
    'name' => null,
])

@php
    $name ??= 'confirm-' . \Illuminate\Support\Str::random(10);
    $danger = $tone === 'danger';
@endphp

<div class="inline-block">
    <x-admin.button
        type="button"
        :size="$size"
        :variant="$variant"
        :icon="$icon"
        x-on:click="$dispatch('open-modal', {{ \Illuminate\Support\Js::from($name) }})"
        {{ $attributes }}
    >
        {{ $slot }}
    </x-admin.button>

    <x-modal :name="$name" maxWidth="md">
        <div class="p-6">
            <div class="flex items-start gap-4">
                <span
                    @class([
                        'flex h-11 w-11 shrink-0 items-center justify-center rounded-full ring-8',
                        'bg-red-50 text-red-600 ring-red-50/60 dark:bg-red-500/10 dark:text-red-400 dark:ring-red-500/5' => $danger,
                        'bg-blue-50 text-blue-600 ring-blue-50/60 dark:bg-blue-500/10 dark:text-blue-400 dark:ring-blue-500/5' => ! $danger,
                    ])
                >
                    <x-admin.icon :name="$icon ?? ($danger ? 'alert-triangle' : 'info')" class="h-5 w-5" />
                </span>
                <div class="min-w-0 pt-0.5">
                    <h2 class="text-base font-semibold text-slate-900 dark:text-white">{{ $title }}</h2>
                    @if ($message)
                        <p class="mt-1.5 text-sm leading-relaxed text-slate-500 dark:text-slate-400">{{ $message }}</p>
                    @endif
                </div>
            </div>

            <div class="mt-6 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                <x-admin.button type="button" variant="secondary" x-on:click="$dispatch('close-modal', {{ \Illuminate\Support\Js::from($name) }})">
                    Cancel
                </x-admin.button>

                <form action="{{ $action }}" method="POST" x-data="{ busy: false }" x-on:submit="busy = true">
                    @csrf
                    @unless (strtoupper($method) === 'POST')
                        @method($method)
                    @endunless

                    <x-admin.button :variant="$danger ? 'danger' : 'primary'" full x-bind:disabled="busy">
                        <span x-text="busy ? 'Working…' : {{ \Illuminate\Support\Js::from($confirm) }}">{{ $confirm }}</span>
                    </x-admin.button>
                </form>
            </div>
        </div>
    </x-modal>
</div>
