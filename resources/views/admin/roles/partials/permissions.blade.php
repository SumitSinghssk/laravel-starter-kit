@can('admin.permissions.view')
    <div x-show="activeTab === 'permissions'" x-cloak class="grid grid-cols-1 items-start gap-6 xl:grid-cols-[minmax(0,1fr)_20rem]">
        @can('admin.permissions.create')
            <x-admin.card
                title="New permission"
                text="Add a permission, then grant it to roles."
                icon="plus"
                class="xl:sticky xl:top-20 xl:order-last"
            >
                <form method="POST" action="{{ route('admin.permissions.store') }}" class="space-y-4">
                    @csrf
                    <x-admin.form.input id="permission_name" name="name" label="Permission name" placeholder="e.g. admin.posts.create">
                        <x-slot:leftIcon>
                            <x-admin.icon name="key" class="h-4 w-4" />
                        </x-slot>
                    </x-admin.form.input>

                    <p class="flex items-start gap-1.5 text-xs leading-relaxed text-slate-500 dark:text-slate-400">
                        <x-admin.icon name="info" class="mt-0.5 h-3.5 w-3.5 shrink-0" />
                        <span>
                            Use dot-notation:
                            <code class="rounded bg-slate-100 px-1 py-px font-mono text-[11px] dark:bg-slate-800">module.resource.action</code>
                            — e.g.
                            <code class="rounded bg-slate-100 px-1 py-px font-mono text-[11px] dark:bg-slate-800">admin.posts.delete</code>
                        </span>
                    </p>

                    <x-admin.button type="submit" variant="primary" icon="plus" full>Create permission</x-admin.button>
                </form>
            </x-admin.card>
        @endcan

        <div class="min-w-0">
            @if ($groupedPermissions->isEmpty())
                <x-admin.card>
                    <div class="flex flex-col items-center justify-center py-10 text-center">
                        <span
                            class="mb-3 flex h-10 w-10 items-center justify-center rounded-xl bg-slate-100 text-slate-400 dark:bg-slate-800 dark:text-slate-500"
                        >
                            <x-admin.icon name="key" class="h-5 w-5" />
                        </span>
                        <p class="text-sm font-medium text-slate-700 dark:text-slate-300">No permissions found</p>
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Create your first permission using the form.</p>
                    </div>
                </x-admin.card>
            @else
                <div class="gap-4 lg:columns-2">
                    @foreach ($groupedPermissions as $group => $groupPerms)
                        <x-admin.card :title="$group" :icon="$groupIcons[$group] ?? 'key'" :padded="false" class="mb-4 break-inside-avoid">
                            <x-slot:extra>
                                <span class="tabular text-xs text-slate-500 dark:text-slate-400">
                                    {{ $groupPerms->count() }} item{{ $groupPerms->count() !== 1 ? 's' : '' }}
                                </span>
                            </x-slot>

                            <div class="divide-y divide-slate-100 dark:divide-slate-800">
                                @foreach ($groupPerms as $permission)
                                    <div class="flex items-center justify-between gap-3 px-4 py-2.5 sm:px-5">
                                        <div class="flex min-w-0 items-center gap-3">
                                            <span
                                                class="flex h-7 w-7 shrink-0 items-center justify-center rounded-md bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400"
                                            >
                                                <x-admin.icon :name="$permissionIcon($permission->name)" class="h-3.5 w-3.5" />
                                            </span>
                                            <div class="min-w-0">
                                                <p class="truncate text-sm font-medium text-slate-900 dark:text-white">
                                                    {{ $permissionLabel($permission->name) }}
                                                </p>
                                                <p class="flex min-w-0 items-center gap-1.5 text-xs text-slate-500 dark:text-slate-400">
                                                    <code class="truncate font-mono text-[11px]">{{ $permission->name }}</code>
                                                    <span class="shrink-0 text-slate-300 dark:text-slate-600">·</span>
                                                    <span class="shrink-0">
                                                        {{ $permission->roles_count }} role{{ $permission->roles_count !== 1 ? 's' : '' }}
                                                    </span>
                                                </p>
                                            </div>
                                        </div>

                                        @can('admin.permissions.delete')
                                            <form
                                                method="POST"
                                                action="{{ route('admin.permissions.destroy', $permission) }}"
                                                onsubmit="return confirm('Delete permission \'{{ $permission->name }}\'?');"
                                            >
                                                @csrf
                                                @method('DELETE')
                                                <button
                                                    type="submit"
                                                    class="flex h-7.5 w-7.5 cursor-pointer items-center justify-center rounded-lg text-slate-400 transition hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-500/10 dark:hover:text-red-400"
                                                    title="Delete permission"
                                                    aria-label="Delete permission {{ $permission->name }}"
                                                >
                                                    <x-admin.icon name="trash" class="h-4 w-4" />
                                                </button>
                                            </form>
                                        @endcan
                                    </div>
                                @endforeach
                            </div>
                        </x-admin.card>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
@endcan
