@php
    $appName = \App\Helpers\Settings::appName();
    $logo = \App\Helpers\Settings::logoLight();
    $user = auth('web')->user();
    $role = $user->getRoleNames()->first();
@endphp

<aside
    :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
    x-effect="
        $el.inert = ! sidebarOpen && ! desktop
        if (sidebarOpen && ! desktop) $nextTick(() => $refs.sidebarClose.focus())
    "
    class="lg:collapsed:w-15 fixed inset-y-0 left-0 z-50 flex h-dvh w-64 -translate-x-full transform flex-col bg-white shadow-xl transition-[transform,width] duration-200 ease-out lg:w-60 lg:translate-x-0 lg:bg-transparent lg:shadow-none dark:bg-slate-950 lg:dark:bg-transparent"
>
    <div class="lg:collapsed:justify-center lg:collapsed:px-0 flex h-15 shrink-0 items-center gap-2 px-3.5">
        <div class="group/logo lg:collapsed:flex-none relative flex min-w-0 flex-1">
            <a href="{{ route('admin.dashboard') }}" class="flex min-w-0 flex-1 items-center gap-2.5" title="{{ $appName }}">
                @if ($logo)
                    <img src="{{ $logo }}" alt="" class="h-8 w-8 shrink-0 rounded-lg object-contain" />
                @else
                    <span
                        class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-linear-to-br from-blue-500 to-blue-700 text-sm font-bold text-white shadow-sm ring-1 shadow-blue-600/30 ring-white/20 ring-inset"
                    >
                        {{ strtoupper(mb_substr($appName, 0, 1)) }}
                    </span>
                @endif
                <span class="lg:collapsed:hidden min-w-0">
                    <span class="block truncate text-sm leading-tight font-semibold text-slate-900 dark:text-white">{{ $appName }}</span>
                    <span class="block text-[11px] leading-tight text-slate-500 dark:text-slate-400">Admin workspace</span>
                </span>
            </a>

            <button
                type="button"
                x-on:click="toggleCollapsed()"
                class="lg:collapsed:flex absolute inset-y-0 left-0 my-auto hidden h-8 w-8 cursor-pointer items-center justify-center rounded-lg bg-white text-slate-600 opacity-0 shadow-xs ring-1 ring-slate-200 transition-opacity duration-150 group-hover/logo:opacity-100 hover:text-slate-900 focus-visible:opacity-100 focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:outline-none dark:bg-slate-900 dark:text-slate-300 dark:ring-slate-700 dark:hover:text-white"
                aria-label="Expand sidebar"
                title="Expand sidebar"
            >
                <x-admin.icon name="chevrons-left" class="h-4 w-4 rotate-180" />
            </button>
        </div>

        <button
            type="button"
            x-on:click="toggleCollapsed()"
            class="lg:collapsed:hidden hidden h-7 w-7 shrink-0 cursor-pointer items-center justify-center rounded-md text-slate-400 transition hover:bg-slate-200/60 hover:text-slate-700 lg:flex dark:hover:bg-slate-800 dark:hover:text-slate-200"
            aria-label="Collapse sidebar"
            title="Collapse sidebar"
        >
            <x-admin.icon name="chevrons-left" class="h-4 w-4" />
        </button>

        <button
            type="button"
            x-ref="sidebarClose"
            x-on:click="sidebarOpen = false"
            class="flex h-7 w-7 cursor-pointer items-center justify-center rounded-md text-slate-400 hover:bg-slate-100 lg:hidden dark:hover:bg-slate-800"
            aria-label="Close menu"
        >
            <x-admin.icon name="x" class="h-4 w-4" />
        </button>
    </div>

    @php
        $groups = collect($links)
            ->map(fn ($section) => [...$section, 'visible' => collect($section['items'])->filter(fn ($link) => ! isset($link['permission']) || $user->can($link['permission']))])
            ->filter(fn ($section) => $section['visible']->isNotEmpty());
        $remembered = request()->hasCookie('admin_nav') ? array_filter(explode('.', (string) request()->cookie('admin_nav'))) : null;
        $openGroups = $groups
            ->mapWithKeys(
                fn ($section) => [
                    $section['key'] => ($section['collapsible'] ?? true) === false || $section['visible']->contains(fn ($link) => request()->routeIs($link['active'])) || ($remembered !== null && in_array($section['key'], $remembered, true)),
                ],
            )
            ->all();
    @endphp

    <nav
        class="custom-scrollbar lg:collapsed:px-2.5 lg:collapsed:space-y-3 flex-1 space-y-1 overflow-y-auto px-3 py-3"
        aria-label="Admin navigation"
        x-data="{
            open: {{ \Illuminate\Support\Js::from($openGroups) }},
            toggle(key) {
                this.open[key] = ! this.open[key]
                const keys = Object.keys(this.open).filter((k) => this.open[k])
                document.cookie =
                    'admin_nav=' +
                    (keys.join('.') || '-') +
                    ';path=/;max-age=31536000;SameSite=Lax'
            },
        }"
    >
        @foreach ($groups as $section)
            @php
                $key = $section['key'];
                $collapsible = $section['collapsible'] ?? true;
                $visibleItems = $section['visible'];
                $isOpen = $openGroups[$key];
                $js = \Illuminate\Support\Js::from($key);
            @endphp

            <div
                data-open="{{ $isOpen ? 'true' : 'false' }}"
                x-bind:data-open="open[{{ $js }}].toString()"
                @class(['group/nav', 'pb-3' => ! $collapsible, 'lg:collapsed:pb-0'])
            >
                @if ($collapsible)
                    <button
                        type="button"
                        x-on:click="toggle({{ $js }})"
                        aria-expanded="{{ $isOpen ? 'true' : 'false' }}"
                        x-bind:aria-expanded="open[{{ $js }}].toString()"
                        aria-controls="nav-{{ $key }}"
                        class="lg:collapsed:hidden flex h-7 w-full cursor-pointer items-center gap-2 rounded-md px-2.5 text-[11px] font-medium text-slate-400 transition hover:bg-slate-200/40 hover:text-slate-700 focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:outline-none dark:text-slate-500 dark:hover:bg-slate-900 dark:hover:text-slate-200"
                    >
                        <span class="flex-1 text-left">{{ $section['section'] }}</span>
                        <span
                            class="tabular rounded bg-slate-200/70 px-1 text-[10px] leading-4 font-semibold text-slate-500 group-data-[open=true]/nav:hidden dark:bg-slate-800 dark:text-slate-400"
                        >
                            {{ $visibleItems->count() }}
                        </span>
                        <x-admin.icon
                            name="chevron-right"
                            class="h-3.5 w-3.5 transition-transform duration-200 group-data-[open=true]/nav:rotate-90"
                        />
                    </button>
                @else
                    <h3 class="lg:collapsed:sr-only mb-1 px-2.5 text-[11px] font-medium text-slate-400 dark:text-slate-500">
                        {{ $section['section'] }}
                    </h3>
                @endif
                <div class="lg:collapsed:block mb-2 hidden h-px bg-slate-200/80 dark:bg-slate-800" aria-hidden="true"></div>

                <div
                    id="nav-{{ $key }}"
                    class="lg:collapsed:grid-rows-[1fr]! grid grid-rows-[0fr] transition-[grid-template-rows] duration-200 ease-out group-data-[open=true]/nav:grid-rows-[1fr]"
                    @unless ($isOpen) inert @endunless
                    x-effect="$el.inert = ! open[{{ $js }}] && ! (collapsed && desktop)"
                >
                    <div class="lg:collapsed:overflow-visible -mx-1 min-h-0 overflow-hidden px-1">
                        <div class="space-y-px py-0.5">
                            @foreach ($visibleItems as $link)
                                @php($active = request()->routeIs($link['active']))
                                <a
                                    href="{{ $link['route'] }}"
                                    title="{{ $link['title'] }}"
                                    @if ($active) aria-current="page" @endif
                                    @class([
                                        'group lg:collapsed:justify-center lg:collapsed:px-0 relative flex h-8.5 items-center gap-2.5 rounded-lg px-2.5 text-sm transition-colors',
                                        'bg-white font-semibold text-slate-900 shadow-xs ring-1 ring-slate-200/80 dark:bg-slate-900 dark:text-white dark:ring-slate-800' => $active,
                                        'font-medium text-slate-600 hover:bg-slate-200/50 hover:text-slate-900 dark:text-slate-400 dark:hover:bg-slate-900 dark:hover:text-slate-100' => ! $active,
                                    ])
                                >
                                    <x-admin.icon
                                        :name="$link['icon']"
                                        @class([
                                            'h-4.5 w-4.5',
                                            'text-blue-600 dark:text-blue-400' => $active,
                                            'text-slate-400 group-hover:text-slate-600 dark:text-slate-500 dark:group-hover:text-slate-300' => ! $active,
                                        ])
                                    />
                                    <span class="lg:collapsed:hidden truncate">{{ $link['title'] }}</span>
                                    @if (! empty($link['badge']))
                                        <span
                                            @if (! empty($link['badgeLabel'])) title="{{ $link['badge'] }} {{ $link['badgeLabel'] }}" @endif
                                            @class([
                                                'tabular lg:collapsed:absolute lg:collapsed:-top-0.5 lg:collapsed:-right-0.5 lg:collapsed:ml-0 lg:collapsed:rounded-full lg:collapsed:px-1 lg:collapsed:leading-4 ml-auto rounded-md px-1.5 text-[10px] leading-4.5 font-semibold text-white',
                                                'bg-red-600 dark:bg-red-500' => ($link['badgeTone'] ?? null) === 'danger',
                                                'bg-blue-600 dark:bg-blue-500' => ($link['badgeTone'] ?? null) !== 'danger',
                                            ])
                                        >
                                            {{ $link['badge'] > 99 ? '99+' : $link['badge'] }}
                                        </span>
                                    @endif
                                </a>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </nav>

    <div class="lg:collapsed:px-2.5 shrink-0 space-y-1 p-3">
        <a
            href="{{ route('home') }}"
            target="_blank"
            rel="noopener"
            title="View website"
            class="group lg:collapsed:justify-center lg:collapsed:px-0 flex h-8.5 items-center gap-2.5 rounded-lg px-2.5 text-sm font-medium text-slate-600 transition-colors hover:bg-slate-200/50 hover:text-slate-900 dark:text-slate-400 dark:hover:bg-slate-900 dark:hover:text-slate-100"
        >
            <x-admin.icon
                name="monitor"
                class="h-4.5 w-4.5 text-slate-400 group-hover:text-slate-600 dark:text-slate-500 dark:group-hover:text-slate-300"
            />
            <span class="lg:collapsed:hidden flex-1">View website</span>
            <x-admin.icon name="arrow-up-right" class="lg:collapsed:hidden h-3.5 w-3.5 text-slate-400" />
        </a>

        <div class="relative" x-data="{ open: false }" x-on:keydown.escape="open = false" x-on:click.outside="open = false">
            <button
                type="button"
                x-on:click="open = ! open"
                x-bind:aria-expanded="open.toString()"
                aria-haspopup="menu"
                aria-label="Account menu for {{ $user->name }}"
                class="lg:collapsed:justify-center lg:collapsed:border-0 lg:collapsed:bg-transparent lg:collapsed:p-0 lg:collapsed:shadow-none flex w-full cursor-pointer items-center gap-2.5 rounded-xl border border-slate-200/80 bg-white p-1.5 text-left shadow-xs transition hover:border-slate-300 dark:border-slate-800 dark:bg-slate-900 dark:hover:border-slate-700"
            >
                <img src="{{ $user->avatar_url }}" alt="" class="h-8 w-8 shrink-0 rounded-lg object-cover" />
                <span class="lg:collapsed:hidden min-w-0 flex-1">
                    <span class="block truncate text-xs font-semibold text-slate-900 dark:text-white">{{ $user->name }}</span>
                    <span class="block truncate text-[11px] text-slate-500 dark:text-slate-400">{{ $role ? ucwords($role) : 'Administrator' }}</span>
                </span>
                <x-admin.icon name="chevrons-up-down" class="lg:collapsed:hidden h-4 w-4 text-slate-400" />
            </button>

            <div
                x-show="open"
                x-cloak
                x-transition:enter="transition duration-150 ease-out"
                x-transition:enter-start="translate-y-1 opacity-0"
                x-transition:enter-end="translate-y-0 opacity-100"
                role="menu"
                class="absolute bottom-full left-0 z-100 mb-2 w-60 rounded-xl border border-slate-200 bg-white p-1.5 shadow-xl dark:border-slate-800 dark:bg-slate-900"
            >
                <div class="flex items-center gap-2.5 px-2 py-2">
                    <img src="{{ $user->avatar_url }}" alt="" class="h-9 w-9 rounded-lg object-cover" />
                    <div class="min-w-0">
                        <p class="truncate text-sm font-semibold text-slate-900 dark:text-white">{{ $user->name }}</p>
                        <p class="truncate text-xs text-slate-500 dark:text-slate-400">{{ $user->email }}</p>
                    </div>
                </div>

                <div class="my-1 h-px bg-slate-100 dark:bg-slate-800"></div>

                @can('profile.view')
                    <a
                        href="{{ route('admin.profile.edit') }}"
                        role="menuitem"
                        class="flex items-center gap-2.5 rounded-lg px-2 py-1.5 text-sm text-slate-700 transition hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800"
                    >
                        <x-admin.icon name="user-circle" class="h-4 w-4 text-slate-400" />
                        My profile
                    </a>
                @endcan

                <a
                    href="{{ route('admin.two-factor.show') }}"
                    role="menuitem"
                    class="flex items-center gap-2.5 rounded-lg px-2 py-1.5 text-sm text-slate-700 transition hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800"
                >
                    <x-admin.icon name="shield-check" class="h-4 w-4 text-slate-400" />
                    Two-factor sign-in
                    <span
                        @class([
                            'ml-auto rounded-full px-1.5 text-[10px] leading-4 font-semibold',
                            'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300' => $user->hasTwoFactor(),
                            'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400' => ! $user->hasTwoFactor(),
                        ])
                    >
                        {{ $user->hasTwoFactor() ? 'On' : 'Off' }}
                    </span>
                </a>

                @can('admin.settings.view')
                    <a
                        href="{{ route('admin.settings.index') }}"
                        role="menuitem"
                        class="flex items-center gap-2.5 rounded-lg px-2 py-1.5 text-sm text-slate-700 transition hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800"
                    >
                        <x-admin.icon name="settings" class="h-4 w-4 text-slate-400" />
                        Settings
                    </a>
                @endcan

                <button
                    type="button"
                    role="menuitem"
                    x-on:click="$store.theme.toggle()"
                    class="flex w-full cursor-pointer items-center gap-2.5 rounded-lg px-2 py-1.5 text-sm text-slate-700 transition hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800"
                >
                    <x-admin.icon name="moon" x-show="! $store.theme.isDark" class="h-4 w-4 text-slate-400" />
                    <x-admin.icon name="sun" x-show="$store.theme.isDark" x-cloak class="h-4 w-4 text-slate-400" />
                    <span x-text="$store.theme.isDark ? 'Light theme' : 'Dark theme'">Dark theme</span>
                </button>

                <div class="my-1 h-px bg-slate-100 dark:bg-slate-800"></div>

                <form method="POST" action="{{ route('admin.logout') }}">
                    @csrf
                    <button
                        type="submit"
                        role="menuitem"
                        class="flex w-full cursor-pointer items-center gap-2.5 rounded-lg px-2 py-1.5 text-sm font-medium text-red-600 transition hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-500/10"
                    >
                        <x-admin.icon name="log-out" class="h-4 w-4" />
                        Log out
                    </button>
                </form>
            </div>
        </div>
    </div>
</aside>
