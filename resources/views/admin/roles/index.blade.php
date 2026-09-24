@php
    $roles = $roles ?? collect();
    $permissions = $permissions ?? collect();
    $groupedPermissions = $groupedPermissions ?? collect();

    $groupIcons = [
        'Activity logs' => 'history',
        'Backups' => 'save',
        'Blog categories' => 'folder-tree',
        'Blogs' => 'newspaper',
        'Dashboard' => 'dashboard',
        'Enquiries' => 'inbox',
        'Galleries' => 'image',
        'Log settings' => 'scroll',
        'Media' => 'folder',
        'Not found' => 'alert-triangle',
        'Notifications' => 'bell',
        'Pages' => 'file-text',
        'Permissions' => 'key',
        'Profile' => 'user-circle',
        'Redirects' => 'redirect',
        'Roles' => 'shield',
        'Seo' => 'globe',
        'Settings' => 'settings',
        'Testimonials' => 'message',
        'Trash' => 'trash',
        'Users' => 'users',
    ];

    $actionIcons = [
        'create' => 'plus',
        'edit' => 'pencil',
        'update' => 'pencil',
        'delete' => 'trash',
        'clear' => 'trash',
        'view' => 'eye',
        'download' => 'download',
        'settings' => 'settings',
        'toogle-status' => 'toggle',
        'download-db' => 'download',
        'clear-cache' => 'refresh',
        'mark-all-as-read' => 'check-circle',
        'update-password' => 'lock',
        'upload' => 'upload',
        'replace' => 'refresh',
        'restore' => 'refresh',
        'manage' => 'settings',
        'sessions' => 'monitor',
    ];

    $permissionLabel = function (string $name) {
        $parts = explode('.', $name);
        $rest = $parts[0] === 'admin' && isset($parts[1]) ? array_slice($parts, 2) : array_slice($parts, 1);

        return $rest ? ucfirst(str_replace(['toogle', '-'], ['toggle', ' '], implode(' · ', $rest))) : $name;
    };

    $permissionIcon = fn (string $name) => $actionIcons[\Illuminate\Support\Str::afterLast($name, '.')] ?? 'key';
@endphp

<x-admin :breadcrumb="[['label' => 'Roles & Permissions', 'url' => '#']]">
    <x-admin.page-header
        title="Roles & permissions"
        description="Group permissions into roles, then assign roles to your team."
        icon="shield-check"
    />

    <div x-data="rolesPermissionsTabs()" class="space-y-6">
        <nav
            class="inline-flex max-w-full items-center gap-1 overflow-x-auto rounded-xl border border-slate-200/80 bg-white p-1 shadow-xs dark:border-slate-800 dark:bg-slate-900"
            aria-label="Tabs"
        >
            <template x-for="tab in tabs" :key="tab.id">
                <button
                    type="button"
                    x-on:click="setTab(tab.id)"
                    :aria-current="activeTab === tab.id ? 'page' : null"
                    :class="activeTab === tab.id
                        ? 'bg-slate-900 text-white shadow-xs dark:bg-white dark:text-slate-900'
                        : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-white'"
                    class="inline-flex cursor-pointer items-center gap-2 rounded-lg px-3.5 py-1.5 text-sm font-medium whitespace-nowrap transition focus:outline-none focus-visible:ring-3 focus-visible:ring-blue-500/30"
                >
                    <span x-show="tab.id === 'roles'"><x-admin.icon name="shield" class="h-4 w-4" /></span>
                    <span x-show="tab.id === 'permissions'"><x-admin.icon name="key" class="h-4 w-4" /></span>
                    <span x-text="tab.label"></span>
                    <span
                        class="tabular rounded-full px-1.5 text-xs"
                        :class="activeTab === tab.id ? 'bg-white/15 dark:bg-slate-900/10' : 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400'"
                        x-text="tab.count"
                    ></span>
                </button>
            </template>
        </nav>

        @include('admin.roles.partials.roles')
        @include('admin.roles.partials.permissions')
    </div>

    <script>
        function rolesPermissionsTabs() {
            return {
                tabs: [
                    @can('admin.roles.view')
                    { id: 'roles', label: 'Roles', count: {{ $roles->count() }} },
                    @endcan
                    @can('admin.permissions.view')
                    { id: 'permissions', label: 'Permissions', count: {{ $permissions->count() }} },
                    @endcan
                ],

                activeTab: null,

                init() {
                    const params = new URLSearchParams(window.location.search);
                    const tabFromUrl = params.get('tab');
                    const validTab = this.tabs.find(t => t.id === tabFromUrl);
                    this.activeTab = validTab ? tabFromUrl : (this.tabs.length ? this.tabs[0].id : null);
                },

                setTab(tabId) {
                    this.activeTab = tabId;
                    const url = new URL(window.location);
                    url.searchParams.set('tab', tabId);
                    window.history.replaceState({}, '', url);
                }
            };
        }

        function permissionToggle(url, permissionName, initialState) {
            return {
                checked: initialState,
                loading: false,

                async toggle() {
                    if (this.loading) return;
                    this.loading = true;
                    try {
                        const response = await fetch(url, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                                'Accept': 'application/json',
                            },
                            body: JSON.stringify({ permission: permissionName }),
                        });
                        const data = await response.json();
                        if (data.success) {
                            this.checked = data.action === 'granted';
                        } else {
                            console.error('Toggle failed', data);
                        }
                    } catch (err) {
                        console.error('Toggle error', err);
                    } finally {
                        this.loading = false;
                    }
                }
            };
        }
    </script>
</x-admin>
