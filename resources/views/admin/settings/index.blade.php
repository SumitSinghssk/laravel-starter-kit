@php
    $settings = $settings ?? [];
    $scriptSettings = $scriptSettings ?? [];

    $tabs = [];
    if (
        auth()
            ->user()
            ->can('admin.settings.basic-details.view')
    ) {
        $tabs[] = [
            'id' => 'basic',
            'label' => 'Business profile',
            'hint' => 'Name, logos, contact & social',
            'icon' => 'building',
            'sections' => [
                ['id' => 'general', 'label' => 'General', 'icon' => 'settings'],
                ['id' => 'branding', 'label' => 'Branding', 'icon' => 'palette'],
                ['id' => 'addresses', 'label' => 'Addresses', 'icon' => 'map-pin'],
                ['id' => 'contact', 'label' => 'Contact', 'icon' => 'phone'],
                ['id' => 'social', 'label' => 'Social', 'icon' => 'share'],
            ],
        ];
    }
    if (
        auth()
            ->user()
            ->can('admin.settings.email.view')
    ) {
        $mail = \App\Support\MailSettings::settings();
        $tabs[] = [
            'id' => 'email',
            'label' => 'Email (SMTP)',
            'hint' => $mail['enabled'] && filled($mail['host']) ? 'On: sending via ' . $mail['host'] : 'Set up how the site sends email',
            'icon' => 'mail',
        ];
    }
    if (
        auth()
            ->user()
            ->can('admin.settings.scripts.view')
    ) {
        $tabs[] = ['id' => 'scripts', 'label' => 'Scripts & CSS', 'hint' => 'Tracking tags and custom styles', 'icon' => 'code'];
    }
    if (
        auth()
            ->user()
            ->can('admin.settings.sitemap.view')
    ) {
        $tabs[] = ['id' => 'sitemap', 'label' => 'Sitemap', 'hint' => 'Generate, download or upload', 'icon' => 'sitemap'];
    }
    if (
        auth()
            ->user()
            ->can('admin.log-settings.view')
    ) {
        $tabs[] = ['id' => 'logs', 'label' => 'Application logs', 'hint' => 'Inspect server log files', 'icon' => 'scroll'];
    }
    if (
        auth()
            ->user()
            ->canany('admin.settings.robots.view')
    ) {
        $tabs[] = ['id' => 'robots', 'label' => 'Robots.txt', 'hint' => 'Crawler rules for search engines', 'icon' => 'bot'];
    }
    if (
        auth()
            ->user()
            ->can('admin.settings.maintenance.view')
    ) {
        $tabs[] = [
            'id' => 'maintenance',
            'label' => 'Maintenance mode',
            'hint' => \App\Support\Maintenance::isOn() ? 'On: visitors see the maintenance page' : 'Take the site offline for visitors',
            'icon' => 'lock',
        ];
    }

    $tabIds = array_column($tabs, 'id');
    $activeTab = in_array(request('tab'), $tabIds, true) ? request('tab') : $tabIds[0] ?? null;
    $cloak = fn (string $id) => $id === $activeTab ? '' : 'x-cloak';
@endphp

<x-admin :breadcrumb="[['label' => 'Settings', 'url' => '#']]">
    <x-admin.page-header title="Settings" description="Manage your business profile, site integrations and system tools." icon="settings">
        @canany(['admin.settings.clear-cache', 'admin.settings.download-db'])
            <x-slot:actions>
                @can('admin.settings.download-db')
                    <div x-data="{ loading: false }">
                        <x-admin.button
                            type="button"
                            variant="secondary"
                            x-on:click="handleDbDownload($el, () => loading = true, () => loading = false)"
                            x-bind:disabled="loading"
                        >
                            <span x-show="!loading" class="flex items-center gap-1.5">
                                <x-admin.icon name="download" class="h-4 w-4" />
                                Download DB
                            </span>

                            <span x-show="loading" x-cloak class="flex items-center gap-1.5">
                                <x-admin.icon name="refresh" class="h-4 w-4 animate-spin" />
                                Preparing backup…
                            </span>
                        </x-admin.button>
                    </div>
                @endcan

                @can('admin.settings.clear-cache')
                    <form method="GET" action="{{ route('admin.settings.clear-cache') }}">
                        <x-admin.button type="submit" variant="danger-outline" icon="refresh">Clear cache</x-admin.button>
                    </form>
                @endcan
            </x-slot>
        @endcanany
    </x-admin.page-header>

    <div
        x-data="settingsTabs({{ \Illuminate\Support\Js::from($activeTab) }})"
        class="grid grid-cols-1 gap-6 lg:grid-cols-[15rem_minmax(0,1fr)] lg:items-start"
    >
        <nav
            aria-label="Settings sections"
            class="-mx-4 border-b border-slate-200 px-4 sm:mx-0 sm:px-0 lg:sticky lg:top-0 lg:border-b-0 dark:border-slate-800"
        >
            <ul class="scrollbar-hide -mb-px flex gap-1 overflow-x-auto lg:mb-0 lg:flex-col lg:overflow-visible">
                @foreach ($tabs as $tab)
                    <li class="shrink-0">
                        <button
                            type="button"
                            x-on:click="setTab(@js($tab['id']))"
                            @if ($tab['id'] === $activeTab) aria-current="page" @endif
                            x-bind:aria-current="activeTab === @js($tab['id']) ? 'page' : null"
                            class="group flex w-full cursor-pointer items-center gap-2.5 border-b-2 border-transparent px-3 py-3 text-left text-sm font-medium whitespace-nowrap text-slate-600 transition hover:text-slate-900 focus:outline-none focus-visible:ring-3 focus-visible:ring-blue-500/30 aria-[current=page]:border-blue-600 aria-[current=page]:text-blue-700! lg:rounded-lg lg:border lg:py-2.5 lg:hover:bg-white/70 lg:aria-[current=page]:border-slate-200/80 lg:aria-[current=page]:bg-white! lg:aria-[current=page]:shadow-xs dark:text-slate-400 dark:hover:text-white dark:aria-[current=page]:border-blue-400 dark:aria-[current=page]:text-blue-300! lg:dark:hover:bg-slate-900/60 lg:dark:aria-[current=page]:border-slate-800 lg:dark:aria-[current=page]:bg-slate-900!"
                        >
                            <span
                                class="flex shrink-0 items-center justify-center text-slate-400 transition group-hover:text-slate-600 group-aria-[current=page]:text-blue-600! lg:h-8 lg:w-8 lg:rounded-lg lg:border lg:border-slate-200 lg:bg-white lg:group-aria-[current=page]:border-blue-100 lg:group-aria-[current=page]:bg-blue-50 dark:group-hover:text-slate-200 dark:group-aria-[current=page]:text-blue-300! lg:dark:border-slate-800 lg:dark:bg-slate-900 lg:dark:group-aria-[current=page]:border-blue-500/20 lg:dark:group-aria-[current=page]:bg-blue-500/10"
                            >
                                <x-admin.icon :name="$tab['icon']" class="h-4 w-4" />
                            </span>
                            <span class="min-w-0">
                                <span class="block">{{ $tab['label'] }}</span>
                                <span class="hidden truncate text-xs font-normal text-slate-500 lg:block dark:text-slate-400">
                                    {{ $tab['hint'] }}
                                </span>
                            </span>
                        </button>

                        @if (! empty($tab['sections']))
                            <ul
                                x-show="activeTab === @js($tab['id'])"
                                {{ $cloak($tab['id']) }}
                                class="mt-1 mb-2 ml-7 hidden space-y-0.5 border-l border-slate-200 pl-3 lg:block dark:border-slate-800"
                            >
                                @foreach ($tab['sections'] as $section)
                                    <li>
                                        <a
                                            href="#settings-{{ $section['id'] }}"
                                            class="flex items-center gap-2 rounded-md px-2 py-1.5 text-xs font-medium text-slate-500 transition hover:bg-white hover:text-slate-900 focus:outline-none focus-visible:ring-3 focus-visible:ring-blue-500/30 dark:text-slate-400 dark:hover:bg-slate-900 dark:hover:text-white"
                                        >
                                            <x-admin.icon :name="$section['icon']" class="h-3.5 w-3.5" />
                                            {{ $section['label'] }}
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </li>
                @endforeach
            </ul>
        </nav>

        <div class="min-w-0">
            @can('admin.settings.basic-details.view')
                <div x-show="activeTab === 'basic'" {{ $cloak('basic') }}>
                    @include('admin.settings.partials.basic-info')
                </div>
            @endcan

            @can('admin.settings.scripts.view')
                <div x-show="activeTab === 'scripts'" {{ $cloak('scripts') }}>
                    @include('admin.settings.partials.scripts', ['settings' => $scriptSettings])
                </div>
            @endcan

            @canany(['admin.settings.sitemap.view', 'admin.settings.sitemap.update'])
                <div x-show="activeTab === 'sitemap'" {{ $cloak('sitemap') }}>
                    @include(
                        'admin.settings.partials.sitemap',
                        [
                            'sitemapInfo' => $sitemapInfo,
                            'sitemapExists' => $sitemapExists,
                        ]
                    )
                </div>
            @endcanany

            @can('admin.log-settings.view')
                <div x-show="activeTab === 'logs'" {{ $cloak('logs') }}>
                    @include('admin.settings.partials.logs')
                </div>
            @endcan

            @can('admin.settings.email.view')
                <div x-show="activeTab === 'email'" {{ $cloak('email') }}>
                    @include('admin.settings.partials.email')
                </div>
            @endcan

            @canany('admin.settings.robots.view')
                <div x-show="activeTab === 'robots'" {{ $cloak('robots') }}>
                    @include('admin.settings.partials.robots')
                </div>
            @endcanany

            @can('admin.settings.maintenance.view')
                <div x-show="activeTab === 'maintenance'" {{ $cloak('maintenance') }}>
                    @include('admin.settings.partials.maintenance')
                </div>
            @endcan
        </div>
    </div>

    @push('scripts')
        <script>
            function handleDbDownload(el, onStart, onEnd) {
                const url = '{{ route('admin.settings.download-db') }}';

                onStart();

                fetch(url, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                })
                    .then((response) => {
                        if (!response.ok) {
                            return response
                                .json()
                                .catch(() => ({}))
                                .then((data) => {
                                    throw new Error(data.message || 'Server error');
                                });
                        }

                        if ((response.headers.get('Content-Type') ?? '').includes('text/html')) {
                            throw new Error('Backup failed. Check logs for details.');
                        }

                        const cd = response.headers.get('Content-Disposition') ?? '';
                        const match = cd.match(/filename[^;=\n]*=['\"](.*?)['\"]|filename=([^;\n]*)/i);
                        const filename = match ? match[1] || match[2] : 'backup.sql';

                        return response.blob().then((blob) => ({ blob, filename }));
                    })
                    .then(({ blob, filename }) => {
                        const a = document.createElement('a');
                        a.href = URL.createObjectURL(blob);
                        a.download = filename;

                        document.body.appendChild(a);
                        a.click();
                        a.remove();

                        URL.revokeObjectURL(a.href);
                        onEnd();
                    })
                    .catch((err) => {
                        onEnd();
                        alert('Download failed: ' + err.message);
                    });
            }
        </script>
    @endpush

    @push('scripts')
        <script>
            function settingsTabs(initial) {
                return {
                    tabs: @js(collect($tabs)->map(fn ($t) => ['id' => $t['id'], 'label' => $t['label']])->values()),

                    activeTab: initial,

                    init() {
                        this.$nextTick(() => {
                            const current = this.$root.querySelector('nav [aria-current=page]');
                            const row = current?.closest('ul');
                            if (current && row && row.scrollWidth > row.clientWidth) {
                                row.scrollLeft = current.offsetLeft - (row.clientWidth - current.offsetWidth) / 2;
                            }
                        });
                    },

                    setTab(tabId) {
                        this.activeTab = tabId;

                        const url = new URL(window.location);
                        url.searchParams.set('tab', tabId);
                        window.history.replaceState({}, '', url);
                    },
                };
            }
        </script>
    @endpush
</x-admin>
