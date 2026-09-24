@props(['items' => null])

@php
    use App\Helpers\Settings;

    $items ??= app(\App\Services\Menus\MenuBuilder::class)->tree('footer');
    $columns = array_values(array_filter($items, fn ($item) => $item['children']));
    $links = array_values(array_filter($items, fn ($item) => ! $item['children']));
    $name = Settings::appName();
    $email = Settings::emails()[0] ?? null;
    $phone = Settings::phones()[0] ?? null;
    $social = array_values(array_filter(Settings::socialLinks(), fn ($s) => filled($s['url'] ?? null) && preg_match('~^https?://~i', $s['url'])));
    $target = fn (array $item) => $item['new_tab'] ? 'target="_blank" rel="noopener"' : '';
@endphp

<footer class="bg-site-footer text-site-footer-text">
    <div class="mx-auto grid max-w-6xl gap-10 px-6 py-14 sm:grid-cols-2 lg:grid-cols-4">
        <div @class(['sm:col-span-2' => count($columns) <= 2, 'lg:col-span-1' => count($columns) >= 3])>
            <p class="font-heading text-site-footer-heading text-lg font-bold">{{ $name }}</p>
            @if ($email || $phone)
                <ul class="mt-4 space-y-1.5 text-sm">
                    @if ($email)
                        <li><a href="mailto:{{ $email }}" class="text-site-footer-link transition hover:opacity-80">{{ $email }}</a></li>
                    @endif

                    @if ($phone)
                        <li>
                            <a href="tel:{{ preg_replace('/[^\d+]/', '', $phone) }}" class="text-site-footer-link transition hover:opacity-80">
                                {{ $phone }}
                            </a>
                        </li>
                    @endif
                </ul>
            @endif

            @if ($social)
                <ul class="mt-5 flex flex-wrap gap-2">
                    @foreach ($social as $profile)
                        <li>
                            <a
                                href="{{ $profile['url'] }}"
                                target="_blank"
                                rel="noopener me"
                                class="rounded-site text-site-footer-link inline-flex border border-white/15 px-3 py-1 text-xs font-medium transition hover:bg-white/10"
                            >
                                {{ $profile['platform'] }}
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        @foreach ($columns as $column)
            <div>
                @if ($column['url'])
                    <a
                        href="{{ $column['url'] }}"
                        {!! $target($column) !!}
                        class="text-site-footer-heading text-sm font-semibold transition hover:opacity-80"
                    >
                        {{ $column['label'] }}
                    </a>
                @else
                    <p class="text-site-footer-heading text-sm font-semibold">{{ $column['label'] }}</p>
                @endif
                <ul class="mt-3 space-y-2 text-sm">
                    @foreach ($column['children'] as $child)
                        <li>
                            @if ($child['url'])
                                <a href="{{ $child['url'] }}" {!! $target($child) !!} class="text-site-footer-link transition hover:opacity-80">
                                    {{ $child['label'] }}
                                </a>
                            @else
                                <span class="text-site-footer-heading font-medium">{{ $child['label'] }}</span>
                            @endif
                            @if ($child['children'])
                                <ul class="mt-2 ml-3 space-y-1.5 border-l border-white/15 pl-3 text-[13px]">
                                    @foreach ($child['children'] as $grandchild)
                                        <li>
                                            <a
                                                href="{{ $grandchild['url'] }}"
                                                {!! $target($grandchild) !!}
                                                class="text-site-footer-text hover:text-site-footer-link transition"
                                            >
                                                {{ $grandchild['label'] }}
                                            </a>
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </div>
        @endforeach
    </div>

    <div class="border-t border-white/10">
        <div class="mx-auto flex max-w-6xl flex-col items-center justify-between gap-3 px-6 py-5 text-xs sm:flex-row">
            <p>© {{ date('Y') }} {{ $name }}. All rights reserved.</p>
            @if ($links)
                <nav aria-label="Footer">
                    <ul class="flex flex-wrap justify-center gap-x-5 gap-y-1">
                        @foreach ($links as $link)
                            <li>
                                <a href="{{ $link['url'] }}" {!! $target($link) !!} class="text-site-footer-link transition hover:opacity-80">
                                    {{ $link['label'] }}
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </nav>
            @endif
        </div>
    </div>
</footer>
