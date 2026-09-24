@php
    use App\Helpers\Settings;

    $appName = Settings::appName();
    $logo = Settings::logoLight();
    $email = Settings::emails()[0] ?? null;
    $phone = Settings::phones()[0] ?? null;
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1" />
        <meta name="robots" content="noindex" />
        <title>{{ $maintenance['title'] }} · {{ $appName }}</title>
        @if ($favicon = Settings::favicon())
            <link rel="icon" href="{{ $favicon }}" />
        @endif

        <script>
            if (window.matchMedia('(prefers-color-scheme: dark)').matches) document.documentElement.classList.add('dark');
        </script>
        @vite(['resources/css/website.css'])
    </head>

    <body class="min-h-screen bg-slate-50 font-sans text-slate-700 antialiased dark:bg-slate-950 dark:text-slate-300">
        @if (! empty($preview))
            <div class="fixed inset-x-0 top-0 z-10 bg-amber-400 px-4 py-2 text-center text-sm font-medium text-amber-950">
                Preview: this is what visitors see while maintenance mode is on.
                <a href="{{ route('admin.settings.index', ['tab' => 'maintenance']) }}" class="ml-1 underline underline-offset-2">
                    Back to settings
                </a>
            </div>
        @endif

        <main class="flex min-h-screen items-center justify-center px-5 py-16">
            <div class="w-full max-w-lg text-center">
                @if ($logo)
                    <img src="{{ $logo }}" alt="{{ $appName }}" class="mx-auto mb-10 h-10 w-auto max-w-44 object-contain" />
                @else
                    <p class="mb-10 text-lg font-semibold tracking-tight text-slate-900 dark:text-white">{{ $appName }}</p>
                @endif

                <div
                    class="mx-auto mb-7 flex h-16 w-16 items-center justify-center rounded-2xl bg-white text-slate-700 shadow-sm ring-1 ring-slate-200 dark:bg-slate-900 dark:text-slate-200 dark:ring-slate-800"
                >
                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.8"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        class="h-7 w-7"
                        aria-hidden="true"
                    >
                        <path
                            d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"
                        />
                    </svg>
                </div>

                <h1 class="text-3xl font-semibold tracking-tight text-balance text-slate-900 sm:text-4xl dark:text-white">
                    {{ $maintenance['title'] }}
                </h1>
                <p class="mx-auto mt-4 max-w-md text-base leading-relaxed text-pretty text-slate-600 dark:text-slate-400">
                    {!! nl2br(e($maintenance['message'])) !!}
                </p>

                @if ($back)
                    <p
                        class="mt-8 inline-flex items-center gap-2 rounded-full bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm ring-1 ring-slate-200 dark:bg-slate-900 dark:text-slate-200 dark:ring-slate-800"
                    >
                        <span class="h-2 w-2 animate-pulse rounded-full bg-emerald-500"></span>
                        Expected back {{ $back->isToday() ? 'today at ' . $back->format('H:i') : $back->format('D j M, H:i') }}
                    </p>
                @endif

                @if ($email || $phone)
                    <p class="mt-10 text-sm text-slate-500 dark:text-slate-400">
                        Need us in the meantime?
                        @if ($email)
                            <a href="mailto:{{ $email }}" class="font-medium text-slate-900 underline-offset-2 hover:underline dark:text-white">
                                {{ $email }}
                            </a>
                        @endif

                        @if ($email && $phone)
                            ·
                        @endif

                        @if ($phone)
                            <a
                                href="tel:{{ preg_replace('/[^0-9+]/', '', $phone) }}"
                                class="font-medium text-slate-900 underline-offset-2 hover:underline dark:text-white"
                            >
                                {{ $phone }}
                            </a>
                        @endif
                    </p>
                @endif
            </div>
        </main>
    </body>
</html>
