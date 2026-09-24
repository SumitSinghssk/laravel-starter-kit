@php
    $failed = $extra['failed'] ?? [];
    $total = $extra['failed_total'] ?? 0;
@endphp

@if ($failed)
    <div class="border-t border-slate-100 dark:border-slate-800">
        <div class="flex flex-wrap items-center justify-between gap-2 px-4 pt-4 sm:px-5">
            <p class="text-xs font-semibold tracking-wide text-slate-400 uppercase">
                Failed jobs
                @if ($total > count($failed))
                    <span class="font-normal normal-case">(latest {{ count($failed) }} of {{ $total }})</span>
                @endif
            </p>
            @if ($canManage)
                <div class="flex gap-2">
                    <x-admin.confirm-button
                        :action="route('admin.system-health.failed-jobs.retry', ['id' => 'all'])"
                        title="Retry all failed jobs?"
                        message="Every failed job goes back into the queue. Fix what made them fail first, or they will fail again."
                        confirm="Retry all"
                        tone="info"
                        icon="refresh"
                    >
                        Retry all
                    </x-admin.confirm-button>
                    <x-admin.confirm-button
                        :action="route('admin.system-health.failed-jobs.delete', ['id' => 'all'])"
                        method="DELETE"
                        title="Delete all failed jobs?"
                        message="They are removed for good and will not run."
                        confirm="Delete all"
                        icon="trash"
                        variant="danger-outline"
                    >
                        Delete all
                    </x-admin.confirm-button>
                </div>
            @endif
        </div>

        <ul class="mt-2 divide-y divide-slate-100 dark:divide-slate-800">
            @foreach ($failed as $job)
                <li class="flex items-start gap-3 px-4 py-3 sm:px-5">
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-medium text-slate-800 dark:text-slate-100">
                            {{ $job['name'] }}
                            <span class="font-normal text-slate-400">· {{ $job['queue'] }} · {{ $job['failed_at']->diffForHumans() }}</span>
                        </p>
                        <p class="mt-0.5 font-mono text-[11px] wrap-break-word text-red-700 dark:text-red-300">{{ $job['error'] }}</p>
                    </div>
                    @if ($canManage)
                        <div class="flex shrink-0 gap-1">
                            <form method="POST" action="{{ route('admin.system-health.failed-jobs.retry', ['id' => $job['id']]) }}">
                                @csrf
                                <x-admin.button size="sm" variant="secondary" icon="refresh" aria-label="Retry {{ $job['name'] }}">
                                    Retry
                                </x-admin.button>
                            </form>
                            <form method="POST" action="{{ route('admin.system-health.failed-jobs.delete', ['id' => $job['id']]) }}">
                                @csrf
                                @method('DELETE')
                                <x-admin.button size="sm" variant="secondary" icon="trash" icon-only aria-label="Delete {{ $job['name'] }}" />
                            </form>
                        </div>
                    @endif
                </li>
            @endforeach
        </ul>
    </div>
@endif
