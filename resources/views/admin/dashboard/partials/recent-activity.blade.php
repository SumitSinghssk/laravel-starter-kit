@can('admin.activity-logs.view')
    @php
        $actionIcons = [
            'login' => ['log-in', 'bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-400'],
            'logout' => ['log-out', 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400'],
            'failed_login' => ['alert-triangle', 'bg-red-50 text-red-600 dark:bg-red-500/10 dark:text-red-400'],
            'created' => ['plus', 'bg-blue-50 text-blue-600 dark:bg-blue-500/10 dark:text-blue-400'],
            'updated' => ['pencil', 'bg-amber-50 text-amber-600 dark:bg-amber-500/10 dark:text-amber-400'],
            'deleted' => ['trash', 'bg-red-50 text-red-600 dark:bg-red-500/10 dark:text-red-400'],
        ];
    @endphp

    <x-admin.card title="Recent activity" icon="activity" :padded="false">
        <x-slot:actions>
            <x-admin.button variant="ghost" size="sm" :href="route('admin.activity-logs.index')">
                View all
                <x-slot:rightIcon>
                    <x-admin.icon name="arrow-right" class="h-3.5 w-3.5" />
                </x-slot>
            </x-admin.button>
        </x-slot>

        <ol class="px-4 py-3 sm:px-5">
            @forelse ($activity as $log)
                @php
                    [$icon, $tone] = $actionIcons[$log->action] ?? ['activity', 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400'];
                @endphp

                <li class="relative flex gap-3 pb-4 last:pb-0">
                    @unless ($loop->last)
                        <span class="absolute top-8 bottom-1 left-3.5 w-px bg-slate-200 dark:bg-slate-800" aria-hidden="true"></span>
                    @endunless

                    <span
                        class="{{ $tone }} relative flex h-7 w-7 shrink-0 items-center justify-center rounded-full ring-4 ring-white dark:ring-slate-900"
                    >
                        <x-admin.icon :name="$icon" class="h-3.5 w-3.5" />
                    </span>
                    <div class="min-w-0 pt-0.5">
                        <p class="text-sm text-slate-600 dark:text-slate-300">
                            <span class="font-medium text-slate-900 dark:text-white">{{ $log->user?->name ?? 'System' }}</span>
                            {{ Str::limit($log->description ?: trim(str_replace('_', ' ', $log->action) . ' ' . $log->model_name), 70) }}
                        </p>
                        <p class="mt-0.5 flex items-center gap-1 text-xs text-slate-400">
                            <x-admin.icon name="clock" class="h-3 w-3" />
                            {{ $log->created_at->diffForHumans() }}
                        </p>
                    </div>
                </li>
            @empty
                <li class="py-10 text-center text-sm text-slate-500">No activity recorded yet.</li>
            @endforelse
        </ol>
    </x-admin.card>
@endcan
