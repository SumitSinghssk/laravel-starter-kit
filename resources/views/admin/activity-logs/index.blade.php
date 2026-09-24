@php
    $canClear = auth()
        ->user()
        ->can('admin.activity-logs.clear');
    $canView = auth()
        ->user()
        ->can('admin.activity-logs.view');

    $actionConfig = [
        'login' => ['Login', 'success', 'log-in'],
        'logout' => ['Logout', 'neutral', 'log-out'],
        'failed_login' => ['Failed login', 'danger', 'x-circle'],
        'viewed' => ['Viewed', 'info', 'eye'],
        'created' => ['Created', 'success', 'plus'],
        'updated' => ['Updated', 'warning', 'pencil'],
        'deleted' => ['Deleted', 'danger', 'trash'],
    ];

    $toneClasses = [
        'success' => 'bg-emerald-50 text-emerald-700 ring-emerald-600/15 dark:bg-emerald-500/10 dark:text-emerald-300 dark:ring-emerald-400/20',
        'neutral' => 'bg-slate-50 text-slate-600 ring-slate-500/15 dark:bg-slate-800 dark:text-slate-300 dark:ring-slate-600/40',
        'info' => 'bg-blue-50 text-blue-700 ring-blue-600/15 dark:bg-blue-500/10 dark:text-blue-300 dark:ring-blue-400/20',
        'warning' => 'bg-amber-50 text-amber-700 ring-amber-600/20 dark:bg-amber-500/10 dark:text-amber-300 dark:ring-amber-400/20',
        'danger' => 'bg-red-50 text-red-700 ring-red-600/15 dark:bg-red-500/10 dark:text-red-300 dark:ring-red-400/20',
    ];

    $actionOptions = collect($actionConfig)
        ->map(fn ($cfg) => $cfg[0])
        ->all();

    $userOptions = $users->mapWithKeys(fn ($user) => [$user->id => $user->name])->all();

    $deviceIcon = fn (?string $type) => match ($type) {
        'mobile' => 'smartphone',
        'tablet' => 'tablet',
        default => 'monitor',
    };

    $headers = ['User', 'Action', 'Detail', 'IP / Location', 'Device', 'Time'];
    if ($canView) {
        $headers[] = 'Actions';
    }
@endphp

<x-admin :breadcrumb="[
    ['label' => 'Activity Logs', 'url' => '#'],
]">
    <x-admin.page-header title="Activity log" description="Track admin actions, sign-ins and system events." icon="activity" :count="$logs->total()">
        @if ($canClear)
            <x-slot:actions>
                <form
                    method="POST"
                    action="{{ route('admin.activity-logs.clear') }}"
                    x-data
                    x-on:submit.prevent="if (confirm('Permanently delete ALL activity logs? This cannot be undone.')) $el.submit();"
                >
                    @csrf
                    @method('DELETE')
                    <x-admin.button variant="danger-outline" type="submit" icon="trash">Clear all logs</x-admin.button>
                </form>
            </x-slot>
        @endif
    </x-admin.page-header>

    <x-admin.table :headers="$headers" :data="$logs" emptyMessage="No activity logs found" emptyIcon="activity">
        <x-slot:toolbar>
            <x-admin.filter.bar :action="route('admin.activity-logs.index')" search="Search by user, page, URL or description…">
                <x-admin.filter.select name="action" label="Action" icon="activity" :options="$actionOptions" />
                <x-admin.filter.select name="user_id" label="User" icon="user" :options="$userOptions" />
            </x-admin.filter.bar>
        </x-slot>

        @foreach ($logs as $log)
            @php
                $isSession = in_array($log->action, ['login', 'logout', 'failed_login']);
                [$label, $tone, $icon] = $actionConfig[$log->action] ?? [ucfirst(str_replace('_', ' ', $log->action)), 'neutral', 'circle'];
                $userName = $log->user?->name ?? 'Guest';
            @endphp

            <tr>
                <td class="max-w-64">
                    <div class="flex items-center gap-3">
                        <span
                            class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-slate-100 text-xs font-semibold text-slate-600 dark:bg-slate-800 dark:text-slate-300"
                        >
                            @if ($log->user)
                                {{ mb_strtoupper(mb_substr($userName, 0, 1)) }}
                            @else
                                <x-admin.icon name="user" class="h-4 w-4" />
                            @endif
                        </span>
                        <div class="min-w-0">
                            <span class="block truncate font-medium text-slate-900 dark:text-white">{{ $userName }}</span>
                            <span class="mt-0.5 flex items-center gap-1 text-xs text-slate-400">
                                <x-admin.icon name="mail" class="h-3 w-3 shrink-0" />
                                <span class="truncate">{{ $log->user?->email ?? ($log->email ?? '—') }}</span>
                            </span>
                        </div>
                    </div>
                </td>

                <td>
                    <div class="flex flex-col items-start gap-1">
                        <span
                            class="{{ $toneClasses[$tone] }} inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium whitespace-nowrap ring-1 ring-inset"
                        >
                            <x-admin.icon :name="$icon" class="h-3 w-3" />
                            {{ $label }}
                        </span>

                        @if ($log->is_suspicious)
                            <span class="inline-flex items-center gap-1 text-[11px] font-medium text-red-600 dark:text-red-400">
                                <x-admin.icon name="alert-triangle" class="h-3 w-3" />
                                Suspicious
                            </span>
                        @endif
                    </div>
                </td>

                <td class="max-w-64">
                    <span class="block truncate text-slate-700 dark:text-slate-200">
                        {{ $log->page_title ?? ($log->model_name ?? ($log->description ?? '—')) }}
                    </span>
                    @if ($log->model_name && $log->page_title)
                        <span class="mt-0.5 flex items-center gap-1 text-xs text-slate-400">
                            <x-admin.icon name="layers" class="h-3 w-3 shrink-0" />
                            <span class="truncate">{{ $log->model_name }}</span>
                        </span>
                    @endif
                </td>

                <td class="whitespace-nowrap">
                    <span class="block font-mono text-xs text-slate-600 dark:text-slate-300">{{ $log->ip_address ?? '—' }}</span>
                    @if ($isSession && $log->city)
                        <span class="mt-0.5 flex items-center gap-1 text-xs text-slate-400">
                            <x-admin.icon name="map-pin" class="h-3 w-3 shrink-0" />
                            {{ collect([$log->city, $log->country_code])->filter()->implode(', ') }}
                        </span>
                    @endif
                </td>

                <td class="whitespace-nowrap">
                    @if ($isSession)
                        <span class="flex items-center gap-1.5 text-xs text-slate-600 dark:text-slate-300">
                            <x-admin.icon :name="$deviceIcon($log->device_type)" class="h-4 w-4 shrink-0 text-slate-400" />
                            <span class="max-w-28 truncate">{{ $log->browser ?? '—' }}</span>
                        </span>
                    @else
                        <span class="text-slate-400">—</span>
                    @endif
                </td>

                <td class="whitespace-nowrap">
                    @if ($log->created_at)
                        <span class="block text-slate-700 dark:text-slate-200" title="{{ $log->created_at_formatted }}">
                            {{ $log->created_at->format('d M Y, H:i') }}
                        </span>
                        <span class="text-xs text-slate-400">{{ $log->created_at->diffForHumans() }}</span>
                    @else
                        <span class="text-slate-400">—</span>
                    @endif
                </td>

                @if ($canView)
                    <td>
                        <x-admin.row-actions size="sm" :viewRoute="route('admin.activity-logs.show', $log->id)" :canView="$canView" />
                    </td>
                @endif
            </tr>
        @endforeach
    </x-admin.table>
</x-admin>
