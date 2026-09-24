@props([
    'url',
    'status',
    'can' => true,
])

@php
    $value = $status instanceof \BackedEnum ? $status->value : (string) $status;
@endphp

@if ($can)
    <button
        type="button"
        role="switch"
        x-data="{ status: @js($value), loading: false }"
        x-bind:aria-checked="(status === 'active').toString()"
        aria-checked="{{ $value === 'active' ? 'true' : 'false' }}"
        x-bind:aria-label="'Status: ' + status + '. Click to change'"
        x-bind:title="
            status === 'active'
                ? 'Active: click to deactivate'
                : 'Inactive: click to activate'
        "
        x-on:click="
            if (loading) return
            loading = true

            axios
                .patch(@js($url))
                .then((res) => {
                    status = res.data.status
                })
                .catch((err) =>
                    window.toast?.(
                        'error',
                        err.response?.data?.message ??
                            'Could not change the status. Please try again.',
                    ),
                )
                .finally(() => (loading = false))
        "
        x-bind:class="loading ? 'cursor-wait opacity-60' : 'cursor-pointer'"
        {{ $attributes->class('group inline-flex items-center gap-2 rounded-full focus:outline-none focus-visible:ring-3 focus-visible:ring-blue-500/30') }}
    >
        <span
            x-bind:class="{
                'bg-emerald-500': status === 'active',
                'bg-slate-300 dark:bg-slate-600': status !== 'active',
            }"
            class="{{ $value === 'active' ? 'bg-emerald-500' : 'bg-slate-300 dark:bg-slate-600' }} relative inline-flex h-4.5 w-8 shrink-0 rounded-full transition-colors"
        >
            <span
                x-bind:class="{
                    'translate-x-3.5': status === 'active',
                    'translate-x-0': status !== 'active',
                }"
                class="{{ $value === 'active' ? 'translate-x-3.5' : 'translate-x-0' }} absolute top-0.5 left-0.5 h-3.5 w-3.5 rounded-full bg-white shadow-sm transition-transform"
            ></span>
        </span>
        <span
            x-text="status === 'active' ? 'Active' : 'Inactive'"
            x-bind:class="{
                'text-emerald-700 dark:text-emerald-300': status === 'active',
                'text-slate-500 dark:text-slate-400': status !== 'active',
            }"
            class="{{ $value === 'active' ? 'text-emerald-700 dark:text-emerald-300' : 'text-slate-500 dark:text-slate-400' }} text-xs font-medium"
        >
            {{ $value === 'active' ? 'Active' : 'Inactive' }}
        </span>
    </button>
@else
    <x-admin.status-badge :status="$value" />
@endif
