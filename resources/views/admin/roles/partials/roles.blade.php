@can('admin.roles.view')
    <div x-show="activeTab === 'roles'" x-cloak class="grid grid-cols-1 items-start gap-6 xl:grid-cols-[minmax(0,1fr)_20rem]">
        @can('admin.roles.create')
            <x-admin.card title="New role" text="Name it, then tick its permissions." icon="plus" class="xl:sticky xl:top-20 xl:order-last">
                <form method="POST" action="{{ route('admin.roles.store') }}" class="space-y-4">
                    @csrf
                    <x-admin.form.input id="role_name" name="name" label="Role name" placeholder="e.g. editor, moderator">
                        <x-slot:leftIcon>
                            <x-admin.icon name="shield" class="h-4 w-4" />
                        </x-slot>
                    </x-admin.form.input>

                    <x-admin.button type="submit" variant="primary" icon="plus" full>Create role</x-admin.button>
                </form>
            </x-admin.card>
        @endcan

        <x-admin.card
            title="All roles"
            :subtitle="(string) $roles->count()"
            text="Expand a role to grant or revoke permissions."
            icon="shield"
            :padded="false"
        >
            @forelse ($roles as $role)
                @php
                    $isSuperRole = in_array($role->name, ['super admin', 'super-admin']);
                    $roleCount = $role->permissions->count();
                @endphp

                <div x-data="{ open: false }" class="border-b border-slate-100 last:border-b-0 dark:border-slate-800">
                    <div class="flex items-center justify-between gap-3 px-4 py-3.5 sm:px-5">
                        <div
                            class="{{ Auth::user()->can('admin.roles.update') ? 'cursor-pointer' : '' }} flex min-w-0 flex-1 items-center gap-3"
                            @can('admin.roles.update') x-on:click="open = !open" @endcan
                        >
                            <span
                                class="{{ $isSuperRole ? 'bg-amber-50 text-amber-600 dark:bg-amber-500/10 dark:text-amber-400' : 'bg-blue-50 text-blue-600 dark:bg-blue-500/10 dark:text-blue-400' }} flex h-9 w-9 shrink-0 items-center justify-center rounded-lg"
                            >
                                <x-admin.icon :name="$isSuperRole ? 'star' : 'shield'" class="h-4.5 w-4.5" />
                            </span>
                            <div class="min-w-0">
                                <p class="flex items-center gap-2 truncate text-sm font-medium text-slate-900 capitalize dark:text-white">
                                    {{ $role->name }}
                                    @if ($isSuperRole)
                                        <span
                                            class="rounded-full bg-amber-50 px-1.5 py-px text-[10px] font-medium text-amber-700 normal-case dark:bg-amber-500/10 dark:text-amber-300"
                                        >
                                            Protected
                                        </span>
                                    @endif
                                </p>
                                <p class="mt-0.5 flex items-center gap-1 text-xs text-slate-500 dark:text-slate-400">
                                    <x-admin.icon name="key" class="h-3 w-3" />
                                    {{ $roleCount }} permission{{ $roleCount !== 1 ? 's' : '' }} assigned
                                </p>
                            </div>
                        </div>

                        <div class="flex shrink-0 items-center gap-1">
                            @can('admin.roles.update')
                                <button
                                    type="button"
                                    x-on:click.stop="open = !open"
                                    class="inline-flex h-8 cursor-pointer items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-2.5 text-xs font-medium text-slate-600 shadow-xs transition hover:border-slate-300 hover:text-slate-900 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300 dark:hover:text-white"
                                    :title="open ? 'Collapse' : 'Manage permissions'"
                                    :aria-label="open ? 'Collapse' : 'Manage permissions'"
                                    :aria-expanded="open.toString()"
                                >
                                    <x-admin.icon name="sliders" class="h-3.5 w-3.5" />
                                    <span class="hidden sm:inline" x-text="open ? 'Close' : 'Permissions'">Permissions</span>
                                    <x-admin.icon
                                        name="chevron-down"
                                        x-bind:class="open ? 'rotate-180' : ''"
                                        class="h-3.5 w-3.5 transition-transform duration-200"
                                    />
                                </button>
                            @endcan

                            @can('admin.roles.delete')
                                @if (! $isSuperRole)
                                    <form
                                        method="POST"
                                        action="{{ route('admin.roles.destroy', $role) }}"
                                        onsubmit="return confirm('Delete role \'{{ $role->name }}\'? This cannot be undone.');"
                                    >
                                        @csrf
                                        @method('DELETE')
                                        <button
                                            type="submit"
                                            class="flex h-8 w-8 cursor-pointer items-center justify-center rounded-lg text-slate-400 transition hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-500/10 dark:hover:text-red-400"
                                            title="Delete role"
                                            aria-label="Delete role {{ $role->name }}"
                                        >
                                            <x-admin.icon name="trash" class="h-4 w-4" />
                                        </button>
                                    </form>
                                @else
                                    <span class="h-8 w-8" aria-hidden="true"></span>
                                @endif
                            @endcan
                        </div>
                    </div>

                    <div
                        x-show="open"
                        x-cloak
                        x-transition:enter="transition duration-200 ease-out"
                        x-transition:enter-start="-translate-y-1 opacity-0"
                        x-transition:enter-end="translate-y-0 opacity-100"
                        x-transition:leave="transition duration-150 ease-in"
                        x-transition:leave-start="translate-y-0 opacity-100"
                        x-transition:leave-end="-translate-y-1 opacity-0"
                    >
                        @can('admin.roles.update')
                            <div class="border-t border-slate-100 bg-slate-50/60 px-4 pt-4 pb-5 sm:px-5 dark:border-slate-800 dark:bg-slate-950/40">
                                <p class="mb-4 flex items-center gap-1.5 text-xs text-slate-500 dark:text-slate-400">
                                    <x-admin.icon name="zap" class="h-3.5 w-3.5 text-amber-500" />
                                    Toggle checkboxes to grant or revoke permissions. Changes are saved instantly.
                                </p>

                                @if ($groupedPermissions->isEmpty())
                                    <p class="text-sm text-slate-400">No permissions found. Create some in the Permissions tab.</p>
                                @else
                                    <div class="grid grid-cols-1 items-start gap-3 lg:grid-cols-2">
                                        @foreach ($groupedPermissions as $group => $groupPerms)
                                            <div class="rounded-xl border border-slate-200/80 bg-white p-3 dark:border-slate-800 dark:bg-slate-900">
                                                <div class="mb-2.5 flex items-center gap-2">
                                                    <span
                                                        class="flex h-6 w-6 shrink-0 items-center justify-center rounded-md border border-slate-200 bg-slate-50 text-slate-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300"
                                                    >
                                                        <x-admin.icon :name="$groupIcons[$group] ?? 'key'" class="h-3.5 w-3.5" />
                                                    </span>
                                                    <h4 class="truncate text-sm font-medium text-slate-800 dark:text-slate-200">{{ $group }}</h4>
                                                    <span class="tabular ml-auto text-xs text-slate-400">{{ $groupPerms->count() }}</span>
                                                </div>
                                                <div class="grid grid-cols-1 gap-1.5 sm:grid-cols-2">
                                                    @foreach ($groupPerms as $permission)
                                                        <label
                                                            x-data="permissionToggle(
                                                                        '{{ route('admin.roles.toggle-permission', $role) }}',
                                                                        '{{ $permission->name }}',
                                                                        {{ $role->hasPermissionTo($permission->name) ? 'true' : 'false' }},
                                                                    )"
                                                            class="flex min-w-0 cursor-pointer items-center gap-2.5 rounded-lg border px-2.5 py-2 transition-colors select-none has-[:focus-visible]:ring-3 has-[:focus-visible]:ring-blue-500/30"
                                                            :class="checked
                                                                ? 'border-blue-300 bg-blue-50 dark:border-blue-500/50 dark:bg-blue-500/10'
                                                                : 'border-slate-200 bg-white hover:border-slate-300 dark:border-slate-700 dark:bg-slate-900 dark:hover:border-slate-600'"
                                                            title="{{ $permission->name }}"
                                                        >
                                                            <input
                                                                type="checkbox"
                                                                class="sr-only"
                                                                :checked="checked"
                                                                x-on:change="toggle()"
                                                                :disabled="loading"
                                                            />
                                                            <span
                                                                class="flex h-4 w-4 shrink-0 items-center justify-center rounded border transition-colors"
                                                                :class="checked
                                                                    ? 'border-blue-600 bg-blue-600 text-white dark:border-blue-500 dark:bg-blue-500'
                                                                    : 'border-slate-300 bg-white text-transparent dark:border-slate-600 dark:bg-slate-800'"
                                                            >
                                                                <x-admin.icon name="check" x-show="checked && !loading" class="h-3 w-3" />
                                                                <x-icons.loading
                                                                    x-show="loading"
                                                                    x-cloak
                                                                    class="h-3 w-3 animate-spin text-blue-500"
                                                                />
                                                            </span>
                                                            <x-admin.icon
                                                                :name="$permissionIcon($permission->name)"
                                                                class="h-3.5 w-3.5 shrink-0 text-slate-400 dark:text-slate-500"
                                                            />
                                                            <span
                                                                class="min-w-0 flex-1 truncate text-xs font-medium"
                                                                :class="checked ? 'text-blue-700 dark:text-blue-300' : 'text-slate-600 dark:text-slate-300'"
                                                            >
                                                                {{ $permissionLabel($permission->name) }}
                                                            </span>
                                                        </label>
                                                    @endforeach
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        @endcan
                    </div>
                </div>
            @empty
                <div class="flex flex-col items-center justify-center px-4 py-14 text-center">
                    <span
                        class="mb-3 flex h-10 w-10 items-center justify-center rounded-xl bg-slate-100 text-slate-400 dark:bg-slate-800 dark:text-slate-500"
                    >
                        <x-admin.icon name="shield-check" class="h-5 w-5" />
                    </span>
                    <p class="text-sm font-medium text-slate-700 dark:text-slate-300">No roles found</p>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Create your first role using the form.</p>
                </div>
            @endforelse
        </x-admin.card>
    </div>
@endcan
