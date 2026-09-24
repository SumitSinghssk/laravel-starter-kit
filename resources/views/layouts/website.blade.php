@php
    $theme = app(\App\Services\Theme\Theme::class);
    $themeCss = rescue(fn () => $theme->stylesheetUrl(), null, report: true);
    $fontLinks = rescue(fn () => $theme->fontLinks(), [], report: false);
    $followsDevice = rescue(fn () => $theme->get()['style']['dark_mode'] === 'auto', false, report: false);
@endphp

<x-app body-class="site-theme antialiased">
    @include('layouts.partials.website.seo')

    @if ($followsDevice)
        @push('heads')
            <script>
                (() => {
                    const dark = window.matchMedia('(prefers-color-scheme: dark)');
                    const apply = () => document.documentElement.classList.toggle('dark', dark.matches);
                    apply();
                    dark.addEventListener('change', apply);
                })();
            </script>
        @endpush
    @endif

    @push('head-scripts')
        @vite(['resources/css/website.css', 'resources/js/website.js'])

        @if ($fontLinks)
            <link rel="preconnect" href="https://fonts.googleapis.com" />
            <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
            @foreach ($fontLinks as $href)
                <link rel="stylesheet" href="{{ $href }}" />
            @endforeach
        @endif

        @if ($themeCss)
            <link rel="stylesheet" href="{{ $themeCss }}" />
        @endif
    @endpush

    @if (auth('web')->check() && rescue(fn () => \App\Support\Maintenance::isOn(), false, report: false))
        <div class="sticky top-0 z-50 bg-amber-400 px-4 py-2 text-center text-sm font-medium text-amber-950">
            Maintenance mode is on: visitors see the maintenance page.
            <a href="{{ route('admin.settings.index', ['tab' => 'maintenance']) }}" class="ml-1 underline underline-offset-2">Turn it off</a>
        </div>
    @endif

    <x-website.header />

    <main class="min-h-[60vh]">
        {{ $slot }}
    </main>

    <x-website.footer />
</x-app>
