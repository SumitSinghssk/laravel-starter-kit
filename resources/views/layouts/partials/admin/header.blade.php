@php
    $crumbs = $breadcrumb ?? [];
@endphp

<header
    class="sticky top-0 z-40 flex shrink-0 items-center justify-between gap-3 border-b border-slate-200/70 bg-slate-50/85 p-4 backdrop-blur-md lg:rounded-t-2xl dark:border-slate-800/70 dark:bg-slate-950/85"
>
    <div class="flex min-w-0 items-center gap-2">
        <button
            type="button"
            x-on:click="sidebarOpen = ! sidebarOpen"
            class="-ml-1 inline-flex h-8 w-8 shrink-0 cursor-pointer items-center justify-center rounded-lg text-slate-500 transition hover:bg-slate-200/60 lg:hidden dark:hover:bg-slate-800"
            aria-label="Open menu"
        >
            <x-admin.icon name="menu" class="h-5 w-5" />
        </button>

        <nav class="flex min-w-0 items-center gap-1 overflow-hidden text-sm whitespace-nowrap" aria-label="Breadcrumb">
            <a
                href="{{ route('admin.dashboard') }}"
                @class([
                    'flex shrink-0 items-center gap-1.5 rounded-md px-1.5 py-1 transition-colors',
                    'text-slate-500 hover:bg-slate-200/50 hover:text-slate-900 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-white' => count($crumbs),
                    'font-semibold text-slate-900 dark:text-white' => ! count($crumbs),
                ])
            >
                <x-admin.icon name="dashboard" class="h-4 w-4" />
                <span @class(['hidden sm:inline' => count($crumbs)])>Dashboard</span>
            </a>
            @foreach ($crumbs as $item)
                <x-admin.icon name="chevron-right" class="h-3.5 w-3.5 text-slate-300 dark:text-slate-600" />

                @if ($loop->last)
                    <span class="truncate px-1.5 py-1 font-semibold text-slate-900 dark:text-white" aria-current="page">{{ $item['label'] }}</span>
                @else
                    <a
                        href="{{ $item['url'] ?? '#' }}"
                        class="truncate rounded-md px-1.5 py-1 text-slate-500 transition-colors hover:bg-slate-200/50 hover:text-slate-900 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-white"
                    >
                        {{ $item['label'] }}
                    </a>
                @endif
            @endforeach
        </nav>
    </div>

    <div class="flex shrink-0 items-center gap-1">
        @if (rescue(fn () => \App\Support\Maintenance::isOn(), false, report: false))
            <a
                href="{{ auth()->user() ?->can('admin.settings.maintenance.view') ? route('admin.settings.index', ['tab' => 'maintenance']) : url('/') }}"
                class="mr-1 inline-flex h-8 items-center gap-1.5 rounded-lg bg-amber-400 px-2.5 text-xs font-semibold text-amber-950 shadow-xs transition hover:bg-amber-300"
                title="Visitors see the maintenance page. Click to change."
            >
                <x-admin.icon name="lock" class="h-3.5 w-3.5" stroke="2.2" />
                <span class="hidden sm:inline">Maintenance on</span>
            </a>
        @endif

        @if (count($quickCreate ?? []))
            <div class="relative" x-data="{ open: false }" x-on:keydown.escape="open = false" x-on:click.outside="open = false">
                <button
                    type="button"
                    x-on:click="open = ! open"
                    x-bind:aria-expanded="open.toString()"
                    aria-haspopup="menu"
                    class="inline-flex h-8 cursor-pointer items-center gap-1.5 rounded-lg bg-slate-900 px-2.5 text-xs font-semibold text-white shadow-xs transition hover:bg-slate-800 dark:bg-white dark:text-slate-900 dark:hover:bg-slate-200"
                >
                    <x-admin.icon name="plus" class="h-4 w-4" stroke="2.2" />
                    <span class="hidden sm:inline">Create</span>
                    <x-admin.icon name="chevron-down" class="hidden h-3.5 w-3.5 opacity-60 sm:block" />
                </button>

                <div
                    x-show="open"
                    x-cloak
                    x-transition:enter="transition duration-150 ease-out"
                    x-transition:enter-start="-translate-y-1 opacity-0"
                    x-transition:enter-end="translate-y-0 opacity-100"
                    role="menu"
                    class="absolute right-0 z-100 mt-2 w-56 rounded-xl border border-slate-200 bg-white p-1.5 shadow-xl dark:border-slate-800 dark:bg-slate-900"
                >
                    <p class="px-2 pt-1 pb-1.5 text-[11px] font-medium text-slate-400">Create new</p>
                    @foreach ($quickCreate as $item)
                        <a
                            href="{{ $item['url'] }}"
                            role="menuitem"
                            class="group flex items-center gap-2.5 rounded-lg px-2 py-1.5 text-sm text-slate-700 transition hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800"
                        >
                            <span
                                class="flex h-7 w-7 items-center justify-center rounded-md border border-slate-200 bg-white text-slate-500 group-hover:text-blue-600 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-400"
                            >
                                <x-admin.icon :name="$item['icon']" class="h-3.5 w-3.5" />
                            </span>
                            {{ $item['title'] }}
                        </a>
                    @endforeach
                </div>
            </div>

            <div class="mx-1.5 h-5 w-px bg-slate-200 dark:bg-slate-800"></div>
        @endif

        <button
            type="button"
            x-data
            x-on:click="$store.theme.toggle()"
            x-bind:aria-label="$store.theme.isDark ? 'Switch to light theme' : 'Switch to dark theme'"
            x-bind:title="$store.theme.isDark ? 'Switch to light theme' : 'Switch to dark theme'"
            class="flex h-8 w-8 cursor-pointer items-center justify-center rounded-lg text-slate-500 transition hover:bg-slate-200/60 hover:text-slate-800 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-white"
        >
            <x-admin.icon name="moon" x-show="! $store.theme.isDark" x-cloak class="h-4.5 w-4.5" />
            <x-admin.icon name="sun" x-show="$store.theme.isDark" x-cloak class="h-4.5 w-4.5" />
        </button>

        @can('admin.notifications.view')
            <div
                class="relative"
                x-data="{ open: false }"
                @click.outside="open = false; $store.notif.close()"
                x-on:keydown.escape.window="
                    open = false
                    $store.notif.close()
                "
            >
                <button
                    type="button"
                    x-on:click="window.innerWidth < 768 ? $store.notif.toggle() : (open = ! open)"
                    x-bind:aria-expanded="open.toString()"
                    aria-label="Notifications"
                    title="Notifications"
                    @class([
                        'relative flex h-8 w-8 cursor-pointer items-center justify-center rounded-lg transition',
                        'bg-blue-50 text-blue-600 dark:bg-blue-500/10 dark:text-blue-400' => request()->routeIs('admin.notifications.*'),
                        'text-slate-500 hover:bg-slate-200/60 hover:text-slate-800 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-white' => ! request()->routeIs(
                            'admin.notifications.*',
                        ),
                    ])
                >
                    <x-admin.icon name="bell" class="h-4.5 w-4.5" />
                    <span
                        x-cloak
                        x-show="$store.notif.unread > 0"
                        x-transition
                        x-text="$store.notif.unread > 9 ? '9+' : $store.notif.unread"
                        class="tabular absolute -top-0.5 -right-0.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-red-500 px-1 text-[9px] font-bold text-white ring-2 ring-slate-50 dark:ring-slate-950"
                    ></span>
                </button>

                <div
                    x-show="open && window.innerWidth >= 768"
                    x-transition:enter="transition duration-150 ease-out"
                    x-transition:enter-start="-translate-y-1 opacity-0"
                    x-transition:enter-end="translate-y-0 opacity-100"
                    x-cloak
                    class="absolute right-0 z-100 mt-2 hidden w-96 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xl md:block dark:border-slate-800 dark:bg-slate-900"
                >
                    @include('layouts.partials.admin.notifications-content')
                </div>
            </div>
        @endcan
    </div>
</header>

@can('admin.notifications.view')
    <div x-data x-show="$store.notif.open" x-cloak class="fixed inset-0 z-60 md:hidden">
        <div
            x-show="$store.notif.open"
            x-transition.opacity
            x-on:click="$store.notif.close()"
            class="absolute inset-0 bg-slate-950/50 backdrop-blur-sm"
        ></div>

        <div
            x-show="$store.notif.open"
            x-transition:enter="transition duration-300 ease-out"
            x-transition:enter-start="translate-y-full"
            x-transition:enter-end="translate-y-0"
            x-transition:leave="transition duration-200 ease-in"
            x-transition:leave-start="translate-y-0"
            x-transition:leave-end="translate-y-full"
            class="absolute right-0 bottom-0 left-0 flex h-[80vh] flex-col overflow-hidden rounded-t-2xl bg-white dark:bg-slate-900"
        >
            <div class="mx-auto mt-2 h-1 w-10 rounded-full bg-slate-200 dark:bg-slate-700"></div>
            <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4 dark:border-slate-800">
                <div>
                    <h2 class="text-base font-semibold text-slate-900 dark:text-white">Notifications</h2>
                    @can('admin.notifications.mark-all-as-read')
                        <button
                            x-show="$store.notif.unread > 0"
                            x-on:click="$store.notif.markAllRead()"
                            class="cursor-pointer text-xs font-medium text-blue-600 hover:underline dark:text-blue-400"
                        >
                            Mark all as read
                        </button>
                    @endcan
                </div>

                <button
                    type="button"
                    aria-label="Close notifications"
                    x-on:click="$store.notif.close()"
                    class="flex h-8 w-8 cursor-pointer items-center justify-center rounded-lg bg-slate-100 text-slate-500 dark:bg-slate-800"
                >
                    <x-admin.icon name="x" class="h-4 w-4" />
                </button>
            </div>

            <div class="flex-1 overflow-y-auto">
                @include('layouts.partials.admin.notifications-content')
            </div>

            <div class="border-t border-slate-100 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                <a
                    href="{{ route('admin.notifications.list') }}"
                    class="block w-full rounded-lg border border-slate-200 px-4 py-2 text-center text-sm font-medium text-slate-700 transition hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800"
                >
                    View all notifications
                </a>
            </div>
        </div>
    </div>

    @push('scripts')
        <script defer>
            document.addEventListener('alpine:init', () => {
                Alpine.store('notif', {
                    open: false,
                    items: [],
                    unread: 0,
                    loaded: false,

                    toggle() {
                        this.open = !this.open;
                    },

                    close() {
                        this.open = false;
                    },

                    fetch() {
                        axios
                            .get('{{ route('admin.notifications.index') }}')
                            .then((res) => {
                                this.items = res.data.notifications;
                                this.unread = res.data.unread_count;
                            })
                            .catch((err) => console.error(err))
                            .finally(() => (this.loaded = true));
                    },

                    markAllRead() {
                        axios.post('{{ route('admin.notifications.markAllRead') }}').then(() => {
                            this.unread = 0;
                            this.items = this.items.map((n) => ({ ...n, seen: true }));
                        });
                    },

                    markRead(id) {
                        const url = '{{ route('admin.notifications.markRead', ':id') }}'.replace(':id', id);

                        axios.post(url).then(() => {
                            const notif = this.items.find((n) => n.id === id);
                            if (notif && !notif.seen) {
                                notif.seen = true;
                                this.unread--;
                            }
                        });
                    },
                });

                Alpine.store('notif').fetch();
            });
        </script>
    @endpush
@endcan
