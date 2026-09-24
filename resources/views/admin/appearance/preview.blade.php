@php
    $name = settings('basic_settings.app_name') ?: config('app.name');
    $button = 'rounded-site inline-flex items-center justify-center gap-2 px-5 py-2.5 text-sm font-semibold transition';
    $menus = app(\App\Services\Menus\MenuBuilder::class);
    $sample = fn (array $labels) => array_map(fn ($label) => ['label' => $label, 'url' => '#', 'new_tab' => false, 'children' => []], $labels);
    $headerItems = $menus->tree('header') ?: [
        ['label' => 'Home', 'url' => url('/'), 'new_tab' => false, 'children' => []],
        ['label' => 'Services', 'url' => '#', 'new_tab' => false, 'children' => $sample(['Design', 'Development', 'Growth'])],
        ...$sample(['Our work', 'About', 'Contact']),
    ];
    $footerItems = $menus->tree('footer') ?: [
        ['label' => 'Company', 'url' => '#', 'new_tab' => false, 'children' => $sample(['About', 'Careers', 'Blog'])],
        ['label' => 'Help', 'url' => '#', 'new_tab' => false, 'children' => $sample(['Contact', 'Privacy', 'Terms'])],
    ];
@endphp

<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1" />
        <meta name="robots" content="noindex" />
        <title>Theme preview</title>
        @vite(['resources/css/website.css', 'resources/js/website.js'])
        @foreach ($fontLinks as $href)
            <link rel="stylesheet" href="{{ $href }}" />
        @endforeach

        <style id="theme-css">
            {!! $css !!}
        </style>
    </head>

    <body class="site-theme antialiased">
        <x-website.header :items="$headerItems" />

        <section class="mx-auto grid max-w-6xl items-center gap-12 px-6 py-20 lg:grid-cols-[1.2fr_1fr]">
            <div>
                <span class="rounded-site bg-site-accent text-site-accent-text inline-flex items-center gap-2 px-3 py-1 text-xs font-semibold">
                    New · Spring collection
                </span>
                <h1 class="mt-5 text-4xl leading-tight tracking-tight sm:text-5xl">Beautiful work, delivered on time.</h1>
                <p class="mt-5 max-w-xl text-lg leading-relaxed">
                    We design and build websites that people enjoy using. Read
                    <a href="#">our story</a>
                    , or see what our clients say below.
                </p>
                <div class="mt-8 flex flex-wrap gap-3">
                    <a href="#" class="{{ $button }} bg-site-button text-site-button-text hover:bg-site-button-hover">Start a project</a>
                    <a href="#" class="{{ $button }} bg-site-button-secondary text-site-button-secondary-text hover:bg-site-button-secondary-hover">
                        See our work
                    </a>
                </div>
                <p class="text-site-muted mt-6 text-sm">Trusted by 1,200+ companies · Replies within one working day</p>
            </div>

            <div class="rounded-site-card border-site-border bg-site-surface border p-6 shadow-sm">
                <p class="text-site-muted text-sm font-medium">This month</p>
                <p class="font-heading text-site-heading mt-1 text-4xl font-bold">98%</p>
                <p class="text-sm">of projects launched on schedule</p>
                <div class="rounded-site bg-site-surface-alt mt-6 h-2.5 overflow-hidden">
                    <div class="rounded-site bg-site-button h-full w-[98%]"></div>
                </div>
                <div class="border-site-border mt-6 grid grid-cols-3 gap-3 border-t pt-5 text-center">
                    <div>
                        <p class="font-heading text-site-heading text-xl font-bold">240</p>
                        <p class="text-site-muted text-xs">Websites</p>
                    </div>
                    <div>
                        <p class="font-heading text-site-heading text-xl font-bold">4.9</p>
                        <p class="text-site-muted text-xs">Rating</p>
                    </div>
                    <div>
                        <p class="font-heading text-site-heading text-xl font-bold">12</p>
                        <p class="text-site-muted text-xs">Awards</p>
                    </div>
                </div>
            </div>
        </section>

        <section class="mx-auto max-w-6xl px-6 pb-20">
            <h2 class="text-3xl tracking-tight">What we do</h2>
            <p class="text-site-muted mt-2">Three ways we can help you grow.</p>
            <div class="mt-8 grid gap-5 md:grid-cols-3">
                @foreach ([['Design', 'Clean, modern layouts that match your brand and guide visitors to act.'], ['Development', 'Fast, secure websites that are easy for your team to update.'], ['Growth', 'Search, speed and analytics, so more of the right people find you.']] as [$title, $text])
                    <article
                        class="rounded-site-card border-site-border bg-site-surface border p-6 transition hover:-translate-y-0.5 hover:shadow-md"
                    >
                        <span class="rounded-site-card bg-site-button/10 text-site-link flex h-10 w-10 items-center justify-center">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-5 w-5">
                                <path d="M12 2l3 7h7l-5.5 4.5L18 21l-6-4-6 4 1.5-7.5L2 9h7z" />
                            </svg>
                        </span>
                        <h3 class="mt-4 text-lg">{{ $title }}</h3>
                        <p class="mt-2 text-sm leading-relaxed">{{ $text }}</p>
                        <a href="#" class="text-site-link hover:text-site-link-hover mt-4 inline-flex text-sm font-semibold">Learn more →</a>
                    </article>
                @endforeach
            </div>
        </section>

        <section class="bg-site-surface-alt">
            <div class="mx-auto max-w-4xl px-6 py-16 text-center">
                <div class="text-site-accent flex justify-center gap-1">
                    @for ($i = 0; $i < 5; $i++)
                        <svg viewBox="0 0 24 24" fill="currentColor" class="h-5 w-5">
                            <path d="M12 2l3 7h7l-5.5 4.5L18 21l-6-4-6 4 1.5-7.5L2 9h7z" />
                        </svg>
                    @endfor
                </div>
                <blockquote class="font-heading text-site-heading mt-5 text-2xl leading-snug">
                    “They understood what we needed from the first call. Our new site brings in twice the enquiries.”
                </blockquote>
                <p class="mt-4 text-sm font-semibold">
                    Priya Nair
                    <span class="text-site-muted font-normal">· Head of Marketing, Finlytic</span>
                </p>
            </div>
        </section>

        <section class="mx-auto grid max-w-6xl gap-10 px-6 py-20 lg:grid-cols-2">
            <div>
                <h2 class="text-3xl tracking-tight">Tell us about your project</h2>
                <p class="mt-2">We usually reply within one working day.</p>

                <div class="mt-6 space-y-3 text-sm">
                    @foreach ([['success', 'Thanks! Your message was sent.'], ['info', 'Tip: add links to sites you like.'], ['warning', 'Only 2 slots left this month.'], ['danger', 'Please enter a valid email address.']] as [$tone, $message])
                        <p
                            @class([
                                'rounded-site-card flex items-center gap-2.5 border px-4 py-3 font-medium',
                                'border-site-success/30 bg-site-success/10 text-site-success' => $tone === 'success',
                                'border-site-info/30 bg-site-info/10 text-site-info' => $tone === 'info',
                                'border-site-warning/30 bg-site-warning/10 text-site-warning' => $tone === 'warning',
                                'border-site-danger/30 bg-site-danger/10 text-site-danger' => $tone === 'danger',
                            ])
                        >
                            <span class="h-2 w-2 shrink-0 rounded-full bg-current"></span>
                            {{ $message }}
                        </p>
                    @endforeach
                </div>
            </div>

            <form class="rounded-site-card border-site-border bg-site-surface space-y-4 border p-6" onsubmit="return false;">
                @php($field = 'mt-1.5 block w-full rounded-site border border-site-input-border bg-site-input px-3.5 py-2.5 text-sm text-site-input-text outline-none transition placeholder:text-site-muted focus:border-site-focus focus:ring-3 focus:ring-site-focus/25')
                <div class="grid gap-4 sm:grid-cols-2">
                    <label class="text-site-heading block text-sm font-medium">
                        Name
                        <input type="text" class="{{ $field }}" value="Priya Nair" />
                    </label>
                    <label class="text-site-heading block text-sm font-medium">
                        Email
                        <input type="email" class="{{ $field }} border-site-danger" placeholder="you@company.com" />
                    </label>
                </div>
                <label class="text-site-heading block text-sm font-medium">
                    Budget
                    <select class="{{ $field }}">
                        <option>₹50,000 – ₹1,00,000</option>
                        <option>Above ₹1,00,000</option>
                    </select>
                </label>
                <label class="text-site-heading block text-sm font-medium">
                    Message
                    <textarea rows="3" class="{{ $field }}" placeholder="What would you like to build?"></textarea>
                </label>
                <label class="flex items-center gap-2 text-sm">
                    <input type="checkbox" checked class="h-4 w-4 accent-(--slot-button)" />
                    Send me the monthly newsletter
                </label>
                <button type="button" class="{{ $button }} bg-site-button text-site-button-text hover:bg-site-button-hover w-full">
                    Send message
                </button>
            </form>
        </section>

        <x-website.footer :items="$footerItems" />

        <script>
            window.addEventListener('message', (event) => {
                if (event.origin !== window.location.origin || event.data?.type !== 'theme-css') return;
                document.getElementById('theme-css').textContent = event.data.css;
            });
            document.addEventListener('click', (event) => {
                if (event.target.closest('a')) event.preventDefault();
            });
        </script>
    </body>
</html>
