@php
    $appName = \App\Helpers\Settings::appName();
    $logo = \App\Helpers\Settings::logoLight();
@endphp

<x-admin>
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
                <h1 class="mt-5 text-xl font-semibold tracking-tight text-slate-900 dark:text-white">Sign in to {{ $appName }}</h1>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Enter your email and password to continue.</p>
            </div>

            <div class="rounded-2xl border border-slate-200/80 bg-white p-6 shadow-sm sm:p-7 dark:border-slate-800 dark:bg-slate-900">
                <form
                    method="POST"
                    action="{{ route('admin.login.store') }}"
                    class="space-y-5"
                    x-data="{ submitting: false }"
                    x-on:submit="submitting = true"
                >
                    @csrf

                    <x-admin.form.input
                        type="email"
                        name="email"
                        label="Email address"
                        placeholder="name@company.com"
                        autocomplete="username"
                        required
                        autofocus
                    />

                    <x-admin.form.input
                        type="password"
                        name="password"
                        label="Password"
                        placeholder="Your password"
                        autocomplete="current-password"
                        required
                    />

                    <x-admin.form.checkbox name="remember" label="Keep me signed in" :checked="old('remember')" />

                    <x-admin.button full size="lg">
                        <span x-text="submitting ? 'Signing in…' : 'Sign in'">Sign in</span>
                    </x-admin.button>
                </form>
            </div>

            <div class="mt-6 flex items-center justify-between text-xs text-slate-400 dark:text-slate-500">
                <span>© {{ date('Y') }} {{ $appName }}</span>
                <a href="{{ route('home') }}" class="inline-flex items-center gap-1 font-medium hover:text-slate-700 dark:hover:text-slate-300">
                    <x-admin.icon name="arrow-left" class="h-3.5 w-3.5" />
                    Back to website
                </a>
            </div>
        </div>
    </div>
</x-admin>
