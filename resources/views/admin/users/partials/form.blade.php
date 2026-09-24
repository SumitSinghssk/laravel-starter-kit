@php
    $isEdit = isset($user);

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

    $totalPermissions = $groupedPermissions->sum(fn ($perms) => $perms->count());
@endphp

<div
    x-data="{
        allRoles: {{ Js::from($rolesWithPermissions) }},
        selectedRoles: {{ Js::from(old('roles', $userRoles ?? [])) }},
        directPermissions:
            {{ Js::from(old('permissions', $userPermissions ?? [])) }},

        init() {
            this.syncRolePermissionsToDir()
        },

        syncRolePermissionsToDir() {
            this.rolePermissions.forEach((p) => {
                if (! this.directPermissions.includes(p)) {
                    this.directPermissions.push(p)
                }
            })
        },

        get rolePermissions() {
            const perms = new Set()
            this.allRoles
                .filter((r) => this.selectedRoles.includes(r.name))
                .forEach((r) => r.permissions.forEach((p) => perms.add(p)))
            return perms
        },

        isFromRole(permName) {
            return this.rolePermissions.has(permName)
        },

        isPermChecked(permName) {
            return this.directPermissions.includes(permName)
        },

        togglePermission(permName) {
            const idx = this.directPermissions.indexOf(permName)
            if (idx === -1) {
                this.directPermissions.push(permName)
            } else {
                this.directPermissions.splice(idx, 1)
            }
        },

        toggleRole(roleName) {
            const idx = this.selectedRoles.indexOf(roleName)
            if (idx === -1) {
                this.selectedRoles.push(roleName)
                const role = this.allRoles.find((r) => r.name === roleName)
                if (role) {
                    role.permissions.forEach((p) => {
                        if (! this.directPermissions.includes(p)) {
                            this.directPermissions.push(p)
                        }
                    })
                }
            } else {
                this.selectedRoles.splice(idx, 1)
            }
        },

        isRoleSelected(roleName) {
            return this.selectedRoles.includes(roleName)
        },
    }"
>
    <template x-for="role in selectedRoles" :key="'r_' + role">
        <input type="hidden" name="roles[]" :value="role" />
    </template>
    <template x-for="perm in directPermissions" :key="'p_' + perm">
        <input type="hidden" name="permissions[]" :value="perm" />
    </template>

    <x-admin.form-grid>
        <x-admin.card title="Profile" text="Who this person is and how they sign in." icon="user">
            <div class="space-y-5">
                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                    <x-admin.form.input name="name" label="Full name" required :value="$user->name ?? ''" placeholder="e.g. John Doe">
                        <x-slot:leftIcon>
                            <x-admin.icon name="user" class="h-4 w-4" />
                        </x-slot>
                    </x-admin.form.input>

                    <x-admin.form.input
                        type="email"
                        name="email"
                        label="Email address"
                        required
                        :value="$user->email ?? ''"
                        placeholder="e.g. john@example.com"
                    >
                        <x-slot:leftIcon>
                            <x-admin.icon name="mail" class="h-4 w-4" />
                        </x-slot>
                    </x-admin.form.input>
                </div>

                <x-admin.form.textarea name="bio" label="Bio" rows="3" :value="$user->bio ?? ''" placeholder="Short bio or description..." />
            </div>
        </x-admin.card>

        <x-admin.card
            title="Password"
            :text="$isEdit ? 'Leave both fields blank to keep the current password.' : 'At least 8 characters.'"
            icon="lock"
        >
            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                <x-admin.form.input
                    type="password"
                    name="password"
                    label="Password"
                    :required="! $isEdit"
                    autocomplete="new-password"
                    :placeholder="$isEdit ? 'Leave blank to keep current' : 'Min. 8 characters'"
                >
                    <x-slot:leftIcon>
                        <x-admin.icon name="lock" class="h-4 w-4" />
                    </x-slot>
                </x-admin.form.input>

                <x-admin.form.input
                    type="password"
                    name="password_confirmation"
                    label="Confirm password"
                    :required="! $isEdit"
                    autocomplete="new-password"
                    :placeholder="$isEdit ? 'Leave blank to keep current' : 'Repeat password'"
                >
                    <x-slot:leftIcon>
                        <x-admin.icon name="lock" class="h-4 w-4" />
                    </x-slot>
                </x-admin.form.input>
            </div>
        </x-admin.card>

        <x-admin.card title="Roles" text="Selecting a role also ticks all of its permissions below." icon="shield">
            <x-slot:extra>
                <span
                    class="tabular inline-flex items-center gap-1 rounded-full border border-slate-200 bg-white px-2 py-px text-xs font-medium text-slate-600 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300"
                >
                    <span x-text="selectedRoles.length">0</span>
                    selected
                </span>
            </x-slot>

            @if ($roles->isEmpty())
                <div
                    class="flex flex-col items-center justify-center rounded-xl border border-dashed border-slate-300 py-10 text-center dark:border-slate-700"
                >
                    <span
                        class="mb-3 flex h-10 w-10 items-center justify-center rounded-xl bg-slate-100 text-slate-400 dark:bg-slate-800 dark:text-slate-500"
                    >
                        <x-admin.icon name="shield" class="h-5 w-5" />
                    </span>
                    <p class="text-sm font-medium text-slate-700 dark:text-slate-300">No roles available</p>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                        <a href="{{ route('admin.roles.index') }}" class="font-medium text-blue-600 hover:underline dark:text-blue-400">
                            Create roles
                        </a>
                        first.
                    </p>
                </div>
            @else
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 2xl:grid-cols-3">
                    @foreach ($roles as $role)
                        <div
                            role="checkbox"
                            tabindex="0"
                            x-bind:aria-checked="isRoleSelected('{{ $role->name }}').toString()"
                            x-on:click="toggleRole('{{ $role->name }}')"
                            x-on:keydown.space.prevent="toggleRole('{{ $role->name }}')"
                            :class="isRoleSelected('{{ $role->name }}')
                                ? 'border-blue-500 bg-blue-50/70 ring-1 ring-blue-500 dark:border-blue-400 dark:bg-blue-500/10 dark:ring-blue-400'
                                : 'border-slate-200 bg-white hover:border-slate-300 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:hover:border-slate-600 dark:hover:bg-slate-800/60'"
                            class="flex cursor-pointer items-center gap-3 rounded-xl border p-3 transition select-none focus:outline-none focus-visible:ring-3 focus-visible:ring-blue-500/30"
                        >
                            <span
                                class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg transition-colors"
                                :class="isRoleSelected('{{ $role->name }}')
                                    ? 'bg-blue-600 text-white dark:bg-blue-500'
                                    : 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400'"
                            >
                                <x-admin.icon name="shield" class="h-4.5 w-4.5" />
                            </span>

                            <div class="min-w-0 flex-1">
                                <p
                                    class="truncate text-sm font-medium capitalize transition-colors"
                                    :class="isRoleSelected('{{ $role->name }}') ? 'text-blue-700 dark:text-blue-300' : 'text-slate-900 dark:text-white'"
                                >
                                    {{ $role->name }}
                                </p>
                                <p class="mt-0.5 flex items-center gap-1 text-xs text-slate-500 dark:text-slate-400">
                                    <x-admin.icon name="key" class="h-3 w-3" />
                                    {{ $role->permissions->count() }} permission{{ $role->permissions->count() !== 1 ? 's' : '' }}
                                </p>
                            </div>

                            <span
                                class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full border transition-colors"
                                :class="isRoleSelected('{{ $role->name }}')
                                    ? 'border-blue-600 bg-blue-600 text-white dark:border-blue-500 dark:bg-blue-500'
                                    : 'border-slate-300 bg-white text-transparent dark:border-slate-600 dark:bg-slate-800'"
                            >
                                <x-admin.icon name="check" class="h-3 w-3" />
                            </span>
                        </div>
                    @endforeach
                </div>
            @endif
        </x-admin.card>

        <x-admin.card title="Permissions" text="Fine-tune access. Permissions from selected roles are pre-ticked." icon="key">
            <x-slot:extra>
                <span
                    class="tabular inline-flex items-center gap-1 rounded-full border border-slate-200 bg-white px-2 py-px text-xs font-medium text-slate-600 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300"
                >
                    <span x-text="directPermissions.length">0</span>
                    / {{ $totalPermissions }}
                </span>
            </x-slot>

            @if ($groupedPermissions->isEmpty())
                <div
                    class="flex flex-col items-center justify-center rounded-xl border border-dashed border-slate-300 py-10 text-center dark:border-slate-700"
                >
                    <span
                        class="mb-3 flex h-10 w-10 items-center justify-center rounded-xl bg-slate-100 text-slate-400 dark:bg-slate-800 dark:text-slate-500"
                    >
                        <x-admin.icon name="key" class="h-5 w-5" />
                    </span>
                    <p class="text-sm font-medium text-slate-700 dark:text-slate-300">No permissions available</p>
                </div>
            @else
                <div class="space-y-3">
                    @foreach ($groupedPermissions as $group => $groupPerms)
                        <div class="rounded-xl border border-slate-200/80 bg-slate-50/60 p-3 dark:border-slate-800 dark:bg-slate-950/40">
                            <div class="mb-2.5 flex items-center justify-between gap-2 px-0.5">
                                <div class="flex min-w-0 items-center gap-2">
                                    <span
                                        class="flex h-6 w-6 shrink-0 items-center justify-center rounded-md border border-slate-200 bg-white text-slate-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300"
                                    >
                                        <x-admin.icon :name="$groupIcons[$group] ?? 'key'" class="h-3.5 w-3.5" />
                                    </span>
                                    <h4 class="truncate text-sm font-medium text-slate-800 dark:text-slate-200">{{ $group }}</h4>
                                </div>
                                <span class="tabular shrink-0 text-xs text-slate-500 dark:text-slate-400">
                                    <span
                                        x-text="
                                            {{ Js::from($groupPerms->pluck('name')->values()) }}.filter((p) =>
                                                isPermChecked(p),
                                            ).length
                                        "
                                    >
                                        0
                                    </span>
                                    / {{ $groupPerms->count() }}
                                </span>
                            </div>

                            <div class="grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-3">
                                @foreach ($groupPerms as $permission)
                                    <div
                                        role="checkbox"
                                        tabindex="0"
                                        x-bind:aria-checked="isPermChecked('{{ $permission->name }}').toString()"
                                        x-on:click="togglePermission('{{ $permission->name }}')"
                                        x-on:keydown.space.prevent="togglePermission('{{ $permission->name }}')"
                                        :class="isPermChecked('{{ $permission->name }}')
                                            ? 'border-blue-300 bg-blue-50 dark:border-blue-500/50 dark:bg-blue-500/10'
                                            : 'border-slate-200 bg-white hover:border-slate-300 dark:border-slate-700 dark:bg-slate-900 dark:hover:border-slate-600'"
                                        class="flex min-w-0 cursor-pointer items-center gap-2.5 rounded-lg border px-2.5 py-2 transition-colors select-none focus:outline-none focus-visible:ring-3 focus-visible:ring-blue-500/30"
                                        title="{{ $permission->name }}"
                                    >
                                        <span
                                            class="flex h-4 w-4 shrink-0 items-center justify-center rounded border transition-colors"
                                            :class="isPermChecked('{{ $permission->name }}')
                                                ? 'border-blue-600 bg-blue-600 text-white dark:border-blue-500 dark:bg-blue-500'
                                                : 'border-slate-300 bg-white text-transparent dark:border-slate-600 dark:bg-slate-800'"
                                        >
                                            <x-admin.icon name="check" class="h-3 w-3" />
                                        </span>

                                        <x-admin.icon
                                            :name="$permissionIcon($permission->name)"
                                            class="h-3.5 w-3.5 shrink-0 text-slate-400 dark:text-slate-500"
                                        />

                                        <span
                                            class="min-w-0 flex-1 truncate text-xs font-medium"
                                            :class="isPermChecked('{{ $permission->name }}')
                                                ? 'text-blue-700 dark:text-blue-300'
                                                : 'text-slate-600 dark:text-slate-300'"
                                        >
                                            {{ $permissionLabel($permission->name) }}
                                        </span>

                                        <span
                                            x-cloak
                                            x-show="isFromRole('{{ $permission->name }}')"
                                            class="shrink-0 rounded-full bg-white px-1.5 py-px text-[10px] font-medium text-slate-500 ring-1 ring-slate-200 dark:bg-slate-800 dark:text-slate-400 dark:ring-slate-700"
                                        >
                                            Role
                                        </span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </x-admin.card>

        <x-slot:aside>
            <x-admin.card title="Avatar" icon="camera">
                <x-admin.image-upload
                    name="avatar"
                    preset="avatar"
                    remove-name="remove_avatar"
                    label="Avatar"
                    :current="isset($user) && $user->avatar ? asset('storage/' . $user->avatar) : null"
                />
            </x-admin.card>

            <x-admin.card title="Account" icon="user-check">
                <x-admin.form.select
                    name="status"
                    label="Status"
                    required
                    :options="\App\Enums\CommonStatusEnum::dotOptions()"
                    :value="$user->status->value ?? \App\Enums\CommonStatusEnum::ACTIVE->value"
                    hint="Inactive users cannot sign in."
                />

                @if ($isEdit)
                    <dl class="mt-5 space-y-2.5 border-t border-slate-100 pt-4 text-xs dark:border-slate-800">
                        <div class="flex items-center justify-between gap-3">
                            <dt class="flex items-center gap-1.5 text-slate-500 dark:text-slate-400">
                                <x-admin.icon name="calendar" class="h-3.5 w-3.5" />
                                Joined
                            </dt>
                            <dd class="font-medium text-slate-700 dark:text-slate-200">{{ $user->created_at?->format('d M Y') }}</dd>
                        </div>
                        <div class="flex items-center justify-between gap-3">
                            <dt class="flex items-center gap-1.5 text-slate-500 dark:text-slate-400">
                                <x-admin.icon name="clock" class="h-3.5 w-3.5" />
                                Last updated
                            </dt>
                            <dd class="font-medium text-slate-700 dark:text-slate-200">{{ $user->updated_at?->diffForHumans() }}</dd>
                        </div>
                    </dl>
                @endif
            </x-admin.card>

            <x-admin.card title="Access summary" icon="shield-check">
                <div class="space-y-4">
                    <div>
                        <p class="mb-2 text-xs font-medium text-slate-500 dark:text-slate-400">Roles</p>
                        <div class="flex flex-wrap gap-1.5">
                            <template x-for="role in selectedRoles" :key="'s_' + role">
                                <span
                                    class="inline-flex items-center gap-1 rounded-full bg-blue-50 px-2 py-0.5 text-xs font-medium text-blue-700 capitalize dark:bg-blue-500/10 dark:text-blue-300"
                                >
                                    <x-admin.icon name="shield" class="h-3 w-3" />
                                    <span x-text="role"></span>
                                </span>
                            </template>
                            <span x-show="selectedRoles.length === 0" class="text-xs text-slate-400 dark:text-slate-500">No roles selected</span>
                        </div>
                    </div>

                    <div class="flex items-center justify-between rounded-lg bg-slate-50 px-3 py-2.5 dark:bg-slate-800/60">
                        <span class="flex items-center gap-1.5 text-xs text-slate-500 dark:text-slate-400">
                            <x-admin.icon name="key" class="h-3.5 w-3.5" />
                            Permissions granted
                        </span>
                        <span class="tabular text-sm font-semibold text-slate-900 dark:text-white">
                            <span x-text="directPermissions.length">0</span>
                            <span class="text-xs font-normal text-slate-400">/ {{ $totalPermissions }}</span>
                        </span>
                    </div>
                </div>
            </x-admin.card>
        </x-slot>
    </x-admin.form-grid>
</div>
