@php
    $canEdit = auth()
        ->user()
        ->can('admin.users.edit');
    $canDelete = auth()
        ->user()
        ->can('admin.users.delete');
    $canToggle = auth()
        ->user()
        ->can('admin.users.toogle-status');
    $canManage = $canEdit || $canDelete;

    $lastActive = app(\App\Services\ActiveSessions::class)->lastActivity($users->pluck('id')->all());

    $headers = ['User', 'Roles', 'Status', 'Joined'];
    if ($canManage) {
        $headers[] = 'Actions';
    }

    $bulk = \App\Http\Controllers\Admin\BulkActionController::allowed('users', auth()->user());
    if ($bulk) {
        array_unshift($headers, ['select' => true]);
    }
@endphp

<x-admin :breadcrumb="[
    ['label' => 'Users', 'url' => route('admin.users.index')]
]">
    <x-admin.page-header title="Users" description="Manage admin accounts, their roles and access." icon="users" :count="$users->total()">
        <x-slot:actions>
            <x-admin.trash-link type="users" />
            <x-admin.transfer-actions type="users" />
            @can('admin.users.create')
                <x-admin.button :href="route('admin.users.create')" icon="plus">New user</x-admin.button>
            @endcan
        </x-slot>
    </x-admin.page-header>

    <x-admin.bulk type="users" :ids="$users->pluck('id')">
        <x-admin.table :headers="$headers" :data="$users" emptyMessage="No users found" emptyIcon="users">
            <x-slot:toolbar>
                @include('admin.users.partials.filters')
            </x-slot>

            @foreach ($users as $user)
                <tr>
                    @if ($bulk)
                        <x-admin.bulk.checkbox :value="$user->id" :label="$user->name" />
                    @endif

                    <td class="max-w-md">
                        <div class="flex items-center gap-3">
                            @php($seen = $lastActive[$user->id] ?? null)
                            <span
                                class="relative shrink-0"
                                @if ($seen) title="{{ $seen->gt(now()->subMinutes(\App\Services\ActiveSessions::ONLINE_MINUTES)) ? 'Active now' : 'Signed in, last active ' . $seen->diffForHumans() }}" @endif
                            >
                                <img
                                    src="{{ $user->avatar_url }}"
                                    alt=""
                                    class="h-9 w-9 rounded-full object-cover ring-1 ring-slate-200 dark:ring-slate-700"
                                />
                                @if ($seen?->gt(now()->subMinutes(\App\Services\ActiveSessions::ONLINE_MINUTES)))
                                    <span
                                        class="absolute -right-0.5 -bottom-0.5 h-3 w-3 rounded-full bg-emerald-500 ring-2 ring-white dark:ring-slate-900"
                                    ></span>
                                @endif
                            </span>

                            <div class="min-w-0">
                                <div class="flex items-center gap-1.5">
                                    @if ($canEdit)
                                        <a
                                            href="{{ route('admin.users.edit', $user) }}"
                                            class="truncate font-medium text-slate-900 hover:text-blue-600 dark:text-white dark:hover:text-blue-400"
                                        >
                                            {{ $user->name }}
                                        </a>
                                    @else
                                        <span class="truncate font-medium text-slate-900 dark:text-white">{{ $user->name }}</span>
                                    @endif

                                    @if ($user->id === auth()->id())
                                        <span
                                            class="shrink-0 rounded-full bg-blue-50 px-1.5 py-px text-[11px] font-medium text-blue-700 dark:bg-blue-500/10 dark:text-blue-300"
                                        >
                                            You
                                        </span>
                                    @endif
                                </div>
                                <span class="mt-0.5 flex items-center gap-1 text-xs text-slate-400">
                                    <x-admin.icon name="mail" class="h-3 w-3 shrink-0" />
                                    <span class="truncate">{{ $user->email }}</span>
                                </span>
                            </div>
                        </div>
                    </td>

                    <td>
                        <div class="flex flex-wrap gap-1">
                            @forelse ($user->roles as $role)
                                <span
                                    class="inline-flex items-center gap-1 rounded-md border border-slate-200 bg-white px-1.5 py-0.5 text-xs font-medium whitespace-nowrap text-slate-600 capitalize dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300"
                                >
                                    <x-admin.icon name="shield" class="h-3 w-3 text-slate-400" />
                                    {{ $role->name }}
                                </span>
                            @empty
                                <span class="text-xs text-slate-400">No roles</span>
                            @endforelse
                        </div>
                    </td>

                    <td>
                        <x-admin.status-toggle :url="route('admin.users.toggle-status', $user->id)" :status="$user->status" :can="$canToggle" />
                    </td>

                    <td class="whitespace-nowrap">
                        @if ($user->created_at)
                            <span class="block text-slate-700 dark:text-slate-200">{{ $user->created_at->format('d M Y') }}</span>
                            <span class="text-xs text-slate-400">{{ $user->created_at->diffForHumans() }}</span>
                        @else
                            <span class="text-slate-400">—</span>
                        @endif
                    </td>

                    @if ($canManage)
                        <td>
                            <x-admin.row-actions
                                delete-message="It will be moved to the Trash, where it can be restored."
                                size="sm"
                                :editRoute="route('admin.users.edit', $user)"
                                :canEdit="$canEdit"
                                :deleteRoute="route('admin.users.destroy', $user)"
                                :deleteId="$user->id"
                                :canDelete="$canDelete && $user->id !== auth()->id()"
                            />
                        </td>
                    @endif
                </tr>
            @endforeach
        </x-admin.table>
    </x-admin.bulk>
</x-admin>
