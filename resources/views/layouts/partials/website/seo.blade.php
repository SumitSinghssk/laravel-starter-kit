@php
    use App\Models\Seo;
    use Illuminate\Support\Facades\Cache;

    $slug = request()->path();

    $seoCollection = Cache::remember('seo_all', now()->addHours(24), fn () => Seo::all()->keyBy('slug'));

    $seo = $seoCollection[$slug] ?? ($seoCollection['default-seo'] ?? null);

    $ogImage = $seo->og_image ?? (settings('basic_settings.logo.light') ?? null);

    $scriptSettings = settings('script_settings') ?? [];
@endphp

@if ($seo)
    @push('heads')
        <link rel="canonical" href="{{ url()->to(request()->path()) }}" />
        <link rel="icon" href="{{ media_url(settings('basic_settings.favicon')) ?? media_url('favicon.ico', 'public') }}" />
        <title>{{ $seo->meta_title ?? settings('basic_settings.app_name') }}</title>
        <meta property="og:type" content="website" />
        <meta property="og:title" content="{{ $seo->meta_title ?? settings('basic_settings.app_name') }}" />

        <meta property="og:url" content="{{ url()->current() }}" />
        <meta property="og:site_name" content="{{ settings('basic_settings.app_name') }}" />
        <meta property="og:locale" content="en_US" />

        <meta name="twitter:card" content="summary_large_image" />
        <meta name="twitter:title" content="{{ $seo->meta_title ?? settings('basic_settings.app_name') }}" />

        @if ($seo->meta_description)
            <meta name="description" content="{{ $seo->meta_description }}" />
            <meta property="og:description" content="{{ $seo->meta_description }}" />
            <meta name="twitter:description" content="{{ $seo->meta_description }}" />
        @endif

        @if ($ogImage)
            <meta property="og:image" content="{{ media_url($ogImage) }}" />
            <meta property="og:image:width" content="1200" />
            <meta property="og:image:height" content="630" />

            <meta name="twitter:image" content="{{ media_url($ogImage) }}" />
        @endif

        <meta property="og:locale" content="en_US" />
        <meta name="robots" content="{{ $seo->index ?? true ? 'index, follow' : 'noindex, nofollow' }}" />

        @if ($seo->custom_css)
            <style>
                {!! $seo->custom_css !!}
            </style>
        @endif

        @if (! empty($scriptSettings['header_css'] ?? null))
            <style>
                {!! $scriptSettings['header_css'] !!}
            </style>
        @endif

        @if (! empty($scriptSettings['header_scripts'] ?? null))
            {!! $scriptSettings['header_scripts'] !!}
        @endif
    @endpush

    @push('head-scripts')
        @if ($seo->header_scripts)
            {!! $seo->header_scripts !!}
        @endif
    @endpush

    @push('scripts')
        @if ($seo->schema)
            {!! $seo->schema !!}
        @endif

        @if ($seo->footer_scripts)
            {!! $seo->footer_scripts !!}
        @endif

        @if (! empty($scriptSettings['footer_scripts'] ?? null))
            {!! $scriptSettings['footer_scripts'] !!}
        @endif

        @if (! empty($scriptSettings['footer_css'] ?? null))
            <style>
                {!! $scriptSettings['footer_css'] !!}
            </style>
        @endif
    @endpush
@endif
