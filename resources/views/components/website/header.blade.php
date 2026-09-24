@props(['items' => null])

@php
    use App\Helpers\Settings;

    $normalize = function (array $items) use (&$normalize) {
        return array_map(
            fn ($item) => [
                'label' => $item['label'] ?? '',
                'url' => $item['url'] ?? null,
                'style' => $item['style'] ?? 'dropdown',
                'image' => $item['image'] ?? null,
                'description' => $item['description'] ?? null,
                'new_tab' => (bool) ($item['new_tab'] ?? false),
                'children' => $normalize($item['children'] ?? []),
            ],
            $items,
        );
    };
    $items = $normalize($items ?? app(\App\Services\Menus\MenuBuilder::class)->tree('header'));
    $name = Settings::appName();
    $logo = Settings::logoLight();
    $current = rtrim(url()->current(), '/');
    $isCurrent = fn (?string $url) => $url && rtrim(strtok($url, '?#'), '/') === $current;
    $inBranch = function (array $item) use (&$inBranch, $isCurrent) {
        return $isCurrent($item['url']) || collect($item['children'])->contains(fn ($child) => $inBranch($child));
    };
    $target = fn (array $item) => $item['new_tab'] ? 'target="_blank" rel="noopener"' : '';
    $hasCards = fn (array $column) => collect($column['children'])->contains(fn ($link) => filled($link['image'] ?? null));
    $chevron = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" class="h-4 w-4"><path d="m6 9 6 6 6-6" /></svg>';
    $arrow = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" class="h-3.5 w-3.5"><path d="m9 6 6 6-6 6" /></svg>';
    $menuLink = 'rounded-site hover:bg-site-surface-alt flex items-center justify-between gap-3 px-3 py-2 text-sm transition';
    $topClass = fn (array $item) => ['rounded-site px-3 py-2 text-sm font-medium whitespace-nowrap transition', 'text-site-header-active' => $inBranch($item), 'text-site-header-text hover:text-site-header-active' => ! $inBranch($item)];
@endphp

<header
    class="border-site-border bg-site-header sticky top-0 z-40 border-b"
    x-data="{ mobile: false }"
    x-on:keydown.escape.window="mobile = false"
>
    <div class="relative mx-auto flex h-16 max-w-6xl items-center justify-between gap-6 px-6">
        <a href="{{ url('/') }}" class="font-heading text-site-header-text flex shrink-0 items-center gap-2.5 text-lg font-bold">
            @if ($logo)
                <img src="{{ $logo }}" alt="{{ $name }}" class="h-9 w-auto max-w-40 object-contain" />
            @else
                <span class="rounded-site-card bg-site-button text-site-button-text flex h-9 w-9 items-center justify-center">
                    {{ mb_strtoupper(mb_substr($name, 0, 1)) }}
                </span>
                {{ $name }}
            @endif
        </a>

        @if ($items)
            <nav class="hidden min-w-0 items-center gap-0.5 lg:flex" aria-label="Main">
                @foreach ($items as $item)
                    @if (! $item['children'])
                        <a
                            href="{{ $item['url'] }}"
                            {!! $target($item) !!}
                            @if ($isCurrent($item['url'])) aria-current="page" @endif
                            @class($topClass($item))
                        >
                            {{ $item['label'] }}
                        </a>

                        @continue
                    @endif

                    @php($mega = $item['style'] === 'mega')
                    <div
                        @class(['relative' => ! $mega])
                        x-data="{ open: false }"
                        x-on:mouseenter="open = true"
                        x-on:mouseleave="open = false"
                        x-on:focusout="$el.contains($event.relatedTarget) || (open = false)"
                    >
                        @if ($item['url'])
                            <div class="flex items-center">
                                <a
                                    href="{{ $item['url'] }}"
                                    {!! $target($item) !!}
                                    @if ($isCurrent($item['url'])) aria-current="page" @endif
                                    @class($topClass($item))
                                >
                                    {{ $item['label'] }}
                                </a>
                                <button
                                    type="button"
                                    x-on:click="open = ! open"
                                    x-bind:aria-expanded="open.toString()"
                                    aria-label="Show links under {{ $item['label'] }}"
                                    class="text-site-header-text hover:text-site-header-active -ml-2 flex h-8 w-6 cursor-pointer items-center justify-center transition"
                                    x-bind:class="open && 'rotate-180'"
                                >
                                    {!! $chevron !!}
                                </button>
                            </div>
                        @else
                            <button
                                type="button"
                                x-on:click="open = ! open"
                                x-bind:aria-expanded="open.toString()"
                                @class([...$topClass($item), 'flex cursor-pointer items-center gap-1'])
                            >
                                {{ $item['label'] }}
                                <span class="transition" x-bind:class="open && 'rotate-180'">{!! $chevron !!}</span>
                            </button>
                        @endif

                        @if ($mega)
                            <div
                                x-show="open"
                                x-cloak
                                x-transition.opacity.duration.150ms
                                class="absolute top-full right-6 z-50 w-max max-w-[calc(100%-3rem)] pt-2"
                            >
                                <div
                                    class="rounded-site-card border-site-border bg-site-surface flex max-h-[calc(100dvh-6rem)] overflow-y-auto border p-6 shadow-xl"
                                >
                                    @foreach ($item['children'] as $column)
                                        @php($cards = $hasCards($column))
                                        <section
                                            @class(['min-w-0 px-6 first:pl-0 last:pr-0', 'border-site-border border-l first:border-l-0', 'shrink-0' => $cards, 'w-52 shrink-0' => ! $cards])
                                        >
                                            @if ($column['url'])
                                                <a
                                                    href="{{ $column['url'] }}"
                                                    {!! $target($column) !!}
                                                    class="font-heading text-site-heading hover:text-site-link text-base font-semibold transition"
                                                >
                                                    {{ $column['label'] }}
                                                </a>
                                            @else
                                                <p class="font-heading text-site-heading text-base font-semibold">{{ $column['label'] }}</p>
                                            @endif

                                            @if ($cards)
                                                <ul
                                                    class="mt-4 grid gap-3"
                                                    style="grid-template-columns: repeat({{ min(count($column['children']), 4) }}, 8.5rem)"
                                                >
                                                    @foreach ($column['children'] as $card)
                                                        <li>
                                                            <a
                                                                href="{{ $card['url'] ?? '#' }}"
                                                                {!! $target($card) !!}
                                                                class="group rounded-site-card border-site-border bg-site-page block overflow-hidden border transition hover:-translate-y-0.5 hover:shadow-md"
                                                            >
                                                                <span class="bg-site-surface-alt block aspect-[4/5] overflow-hidden">
                                                                    @if ($card['image'])
                                                                        <img
                                                                            src="{{ $card['image'] }}"
                                                                            alt=""
                                                                            loading="lazy"
                                                                            class="h-full w-full object-cover transition duration-300 group-hover:scale-105"
                                                                        />
                                                                    @endif
                                                                </span>
                                                                <span class="block p-2.5">
                                                                    <span class="text-site-heading line-clamp-2 text-xs leading-snug">
                                                                        {{ $card['label'] }}
                                                                    </span>
                                                                    @if ($card['description'])
                                                                        <span class="text-site-heading mt-1 block text-xs font-bold">
                                                                            {{ $card['description'] }}
                                                                        </span>
                                                                    @endif
                                                                </span>
                                                            </a>
                                                        </li>
                                                    @endforeach
                                                </ul>
                                            @elseif ($column['children'])
                                                <ul class="mt-3 space-y-0.5">
                                                    @foreach ($column['children'] as $link)
                                                        @if ($link['url'])
                                                            <li>
                                                                <a
                                                                    href="{{ $link['url'] }}"
                                                                    {!! $target($link) !!}
                                                                    @if ($isCurrent($link['url'])) aria-current="page" @endif
                                                                    @class(['rounded-site hover:bg-site-surface-alt -mx-2 block px-2 py-1.5 text-sm transition', 'text-site-header-active font-semibold' => $isCurrent($link['url']), 'text-site-text hover:text-site-heading' => ! $isCurrent($link['url'])])
                                                                >
                                                                    {{ $link['label'] }}
                                                                </a>
                                                            </li>
                                                        @endif
                                                    @endforeach
                                                </ul>
                                            @endif
                                        </section>
                                    @endforeach
                                </div>
                            </div>
                        @else
                            <div x-show="open" x-cloak x-transition.opacity.duration.150ms class="absolute top-full left-0 z-50 min-w-56 pt-2">
                                <ul class="rounded-site-card border-site-border bg-site-surface border p-1.5 shadow-lg">
                                    @foreach ($item['children'] as $child)
                                        <li
                                            class="relative"
                                            @if ($child['children']) x-data="{ sub: false }" x-on:mouseenter="sub = true" x-on:mouseleave="sub = false" x-on:focusout="$el.contains($event.relatedTarget) || (sub = false)" @endif
                                        >
                                            <div class="flex items-center">
                                                @if ($child['url'])
                                                    <a
                                                        href="{{ $child['url'] }}"
                                                        {!! $target($child) !!}
                                                        @if ($isCurrent($child['url'])) aria-current="page" @endif
                                                        @class([$menuLink, 'flex-1', 'text-site-header-active font-semibold' => $inBranch($child), 'text-site-text hover:text-site-heading' => ! $inBranch($child)])
                                                    >
                                                        {{ $child['label'] }}
                                                    </a>
                                                    @if ($child['children'])
                                                        <button
                                                            type="button"
                                                            x-on:click="sub = ! sub"
                                                            x-bind:aria-expanded="sub.toString()"
                                                            aria-label="Show links under {{ $child['label'] }}"
                                                            class="rounded-site text-site-muted hover:bg-site-surface-alt hover:text-site-heading flex h-8 w-7 shrink-0 cursor-pointer items-center justify-center transition"
                                                        >
                                                            {!! $arrow !!}
                                                        </button>
                                                    @endif
                                                @else
                                                    <button
                                                        type="button"
                                                        x-on:click="sub = ! sub"
                                                        x-bind:aria-expanded="sub.toString()"
                                                        @class([$menuLink, 'w-full cursor-pointer text-left', 'text-site-header-active font-semibold' => $inBranch($child), 'text-site-text hover:text-site-heading' => ! $inBranch($child)])
                                                    >
                                                        {{ $child['label'] }}
                                                        <span class="text-site-muted">{!! $arrow !!}</span>
                                                    </button>
                                                @endif
                                            </div>

                                            @if ($child['children'])
                                                <div
                                                    x-show="sub"
                                                    x-cloak
                                                    x-transition.opacity.duration.150ms
                                                    class="absolute top-0 left-full z-50 min-w-52 pl-2"
                                                >
                                                    <ul class="rounded-site-card border-site-border bg-site-surface border p-1.5 shadow-lg">
                                                        @foreach ($child['children'] as $grandchild)
                                                            <li>
                                                                <a
                                                                    href="{{ $grandchild['url'] }}"
                                                                    {!! $target($grandchild) !!}
                                                                    @if ($isCurrent($grandchild['url'])) aria-current="page" @endif
                                                                    @class([$menuLink, 'text-site-header-active font-semibold' => $isCurrent($grandchild['url']), 'text-site-text hover:text-site-heading' => ! $isCurrent($grandchild['url'])])
                                                                >
                                                                    {{ $grandchild['label'] }}
                                                                </a>
                                                            </li>
                                                        @endforeach
                                                    </ul>
                                                </div>
                                            @endif
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                    </div>
                @endforeach
            </nav>

            <button
                type="button"
                x-on:click="mobile = ! mobile"
                x-bind:aria-expanded="mobile.toString()"
                aria-controls="site-mobile-menu"
                aria-label="Menu"
                class="rounded-site text-site-header-text hover:bg-site-surface-alt flex h-10 w-10 cursor-pointer items-center justify-center lg:hidden"
            >
                <svg x-show="! mobile" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-5 w-5">
                    <path d="M4 6h16M4 12h16M4 18h16" />
                </svg>
                <svg x-show="mobile" x-cloak viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-5 w-5">
                    <path d="M6 6l12 12M18 6 6 18" />
                </svg>
            </button>
        @endif
    </div>

    @if ($items)
        <nav
            id="site-mobile-menu"
            x-show="mobile"
            x-cloak
            x-transition.opacity
            class="border-site-border bg-site-header max-h-[calc(100dvh-4rem)] overflow-y-auto border-t px-6 py-3 lg:hidden"
            aria-label="Main"
        >
            <ul class="space-y-1">
                @foreach ($items as $item)
                    <li>
                        @if ($item['url'])
                            <a
                                href="{{ $item['url'] }}"
                                {!! $target($item) !!}
                                @class(['rounded-site block px-3 py-2.5 text-base font-medium', 'text-site-header-active' => $inBranch($item), 'text-site-header-text' => ! $inBranch($item)])
                            >
                                {{ $item['label'] }}
                            </a>
                        @else
                            <p class="text-site-muted px-3 pt-3 pb-1.5 text-xs font-semibold tracking-wide uppercase">{{ $item['label'] }}</p>
                        @endif
                        @if ($item['children'])
                            <ul class="border-site-border mb-2 ml-3 space-y-0.5 border-l pl-3">
                                @foreach ($item['children'] as $child)
                                    <li>
                                        @if ($child['url'])
                                            <a
                                                href="{{ $child['url'] }}"
                                                {!! $target($child) !!}
                                                @class(['rounded-site block px-3 py-2 text-sm', 'text-site-header-active font-semibold' => $inBranch($child), 'text-site-header-text' => ! $inBranch($child)])
                                            >
                                                {{ $child['label'] }}
                                            </a>
                                        @else
                                            <p class="text-site-heading px-3 pt-2 pb-1 text-sm font-semibold">{{ $child['label'] }}</p>
                                        @endif
                                        @if ($child['children'])
                                            <ul class="border-site-border mb-1 ml-3 space-y-0.5 border-l pl-3">
                                                @foreach ($child['children'] as $grandchild)
                                                    <li>
                                                        <a
                                                            href="{{ $grandchild['url'] ?? '#' }}"
                                                            {!! $target($grandchild) !!}
                                                            @class(['rounded-site flex items-center gap-3 px-3 py-1.5 text-sm', 'text-site-header-active font-semibold' => $isCurrent($grandchild['url']), 'text-site-muted' => ! $isCurrent($grandchild['url'])])
                                                        >
                                                            @if ($grandchild['image'])
                                                                <img
                                                                    src="{{ $grandchild['image'] }}"
                                                                    alt=""
                                                                    loading="lazy"
                                                                    class="rounded-site h-12 w-10 shrink-0 object-cover"
                                                                />
                                                            @endif

                                                            <span class="min-w-0">
                                                                <span class="block truncate">{{ $grandchild['label'] }}</span>
                                                                @if ($grandchild['description'])
                                                                    <span class="text-site-heading block text-xs font-bold">
                                                                        {{ $grandchild['description'] }}
                                                                    </span>
                                                                @endif
                                                            </span>
                                                        </a>
                                                    </li>
                                                @endforeach
                                            </ul>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </li>
                @endforeach
            </ul>
        </nav>
    @endif
</header>
