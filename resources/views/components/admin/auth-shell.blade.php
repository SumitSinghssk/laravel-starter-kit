@props([
    'heading',
    'intro',
])

@php
    $appName = \App\Helpers\Settings::appName();
    $logo = \App\Helpers\Settings::logoLight();
@endphp

<div class="flex min-h-screen flex-col items-center justify-center bg-slate-50 px-4 py-10 dark:bg-slate-950">
    <div class="w-full max-w-sm">
        <div class="mb-8 flex flex-col items-center text-center">
            @if ($logo)
                <img src="{{ $logo }}" alt="{{ $appName }}" class="h-11 w-auto max-w-40 object-contain" />
            @else
                <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-blue-600 text-lg font-bold text-white shadow-sm">
                    {{ strtoupper(mb_substr($appName, 0, 1)) }}
                </span>
            @endif
            <h1 class="mt-5 text-xl font-semibold tracking-tight text-slate-900 dark:text-white">{{ $heading }}</h1>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $intro }}</p>
        </div>

        {{ $slot }}

        <div class="mt-6 flex items-center justify-between text-xs text-slate-400 dark:text-slate-500">
            <span>© {{ date('Y') }} {{ $appName }}</span>
            <a href="{{ route('admin.login') }}" class="inline-flex items-center gap-1 font-medium hover:text-slate-700 dark:hover:text-slate-300">
                <x-admin.icon name="arrow-left" class="h-3.5 w-3.5" />
                Back to sign in
            </a>
        </div>
    </div>
</div>
