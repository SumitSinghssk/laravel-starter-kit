@php
    use App\Services\Health\SystemHealth;
    use Illuminate\Support\Carbon;

    $canManage = auth()
        ->user()
        ->can('admin.system-health.manage');
    $canEmailSettings = auth()
        ->user()
        ->can('admin.settings.email.view');
    $canLogs = auth()
        ->user()
        ->can('admin.log-settings.view');
    $canBackups = auth()
        ->user()
        ->can('admin.backups.view');

    $tone = [
        SystemHealth::OK => ['icon' => 'check-circle', 'text' => 'text-emerald-600 dark:text-emerald-400', 'pill' => 'bg-emerald-50 text-emerald-700 ring-emerald-600/15 dark:bg-emerald-500/10 dark:text-emerald-300 dark:ring-emerald-400/20', 'label' => 'Healthy'],
        SystemHealth::INFO => ['icon' => 'info', 'text' => 'text-slate-400 dark:text-slate-500', 'pill' => 'bg-slate-50 text-slate-600 ring-slate-500/15 dark:bg-slate-800 dark:text-slate-300 dark:ring-slate-400/20', 'label' => 'Info'],
        SystemHealth::WARNING => ['icon' => 'alert-triangle', 'text' => 'text-amber-500 dark:text-amber-400', 'pill' => 'bg-amber-50 text-amber-700 ring-amber-600/20 dark:bg-amber-500/10 dark:text-amber-300 dark:ring-amber-400/20', 'label' => 'Warning'],
        SystemHealth::ERROR => ['icon' => 'x-circle', 'text' => 'text-red-600 dark:text-red-400', 'pill' => 'bg-red-50 text-red-700 ring-red-600/15 dark:bg-red-500/10 dark:text-red-300 dark:ring-red-400/20', 'label' => 'Problem'],
    ];

    $attention = collect($groups)
        ->flatMap(fn ($group) => collect($group['checks'])->map(fn ($check) => [...$check, 'group' => $group['label'], 'groupKey' => $group['key']]))
        ->filter(fn ($check) => in_array($check['status'], [SystemHealth::ERROR, SystemHealth::WARNING], true))
        ->sortBy(fn ($check) => $check['status'] === SystemHealth::ERROR ? 0 : 1)
        ->values();

    $headline = match ($summary['status']) {
        SystemHealth::ERROR => $summary['errors'] === 1 ? '1 problem needs attention' : $summary['errors'] . ' problems need attention',
        SystemHealth::WARNING => 'Working, with ' . $summary['warnings'] . ' ' . str('warning')->plural($summary['warnings']),
        default => 'Everything looks good',
    };

    $cron = fn (string $expression) => match ($expression) {
        '* * * * *' => 'Every minute',
        '*/5 * * * *' => 'Every 5 minutes',
        '0 * * * *' => 'Every hour',
        '0 0 * * *' => 'Every day at 00:00',
        '0 0 * * 0' => 'Every Sunday',
        '0 0 1 * *' => 'Every month',
        default => $expression,
    };
@endphp

<x-admin :breadcrumb="[['label' => 'System health', 'url' => route('admin.system-health.index')]]">
    <x-admin.page-header
        title="System health"
        description="Server, database, storage, queue, scheduler and email, checked in one place."
        icon="heart-pulse"
    >
        <x-slot:actions>
            <div
                x-data="{
                    state: 'idle',
                    async copy() {
                        this.state = (await window.copyText(this.$refs.report.value))
                            ? 'copied'
                            : 'failed'
                        setTimeout(() => (this.state = 'idle'), 2000)
                    },
                }"
            >
                <textarea x-ref="report" class="hidden" aria-hidden="true" tabindex="-1">{{ $report }}</textarea>
                <x-admin.button type="button" variant="secondary" x-on:click="copy()">
                    <x-slot:leftIcon>
                        <x-admin.icon name="copy" class="h-4 w-4" x-show="state === 'idle'" />
                        <x-admin.icon name="check" class="h-4 w-4 text-emerald-500" x-show="state === 'copied'" x-cloak />
                        <x-admin.icon name="x-circle" class="h-4 w-4 text-red-500" x-show="state === 'failed'" x-cloak />
                    </x-slot>
                    <span x-text="{ idle: 'Copy report', copied: 'Copied', failed: 'Copy failed' }[state]">Copy report</span>
                </x-admin.button>
            </div>
            <x-admin.button :href="route('admin.system-health.index')" icon="refresh">Re-run checks</x-admin.button>
        </x-slot>
    </x-admin.page-header>

    <section
        @class([
            'mb-6 overflow-hidden rounded-xl border shadow-xs',
            'border-red-200 bg-red-50/60 dark:border-red-500/30 dark:bg-red-500/5' => $summary['status'] === SystemHealth::ERROR,
            'border-amber-200 bg-amber-50/60 dark:border-amber-500/30 dark:bg-amber-500/5' => $summary['status'] === SystemHealth::WARNING,
            'border-emerald-200 bg-emerald-50/60 dark:border-emerald-500/30 dark:bg-emerald-500/5' => $summary['status'] === SystemHealth::OK,
        ])
    >
        <div class="flex flex-col gap-4 p-5 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-center gap-4">
                <span
                    @class([
                        'flex h-12 w-12 shrink-0 items-center justify-center rounded-full ring-8',
                        'bg-red-100 text-red-600 ring-red-100/50 dark:bg-red-500/15 dark:text-red-400 dark:ring-red-500/5' => $summary['status'] === SystemHealth::ERROR,
                        'bg-amber-100 text-amber-600 ring-amber-100/50 dark:bg-amber-500/15 dark:text-amber-400 dark:ring-amber-500/5' =>
                            $summary['status'] === SystemHealth::WARNING,
                        'bg-emerald-100 text-emerald-600 ring-emerald-100/50 dark:bg-emerald-500/15 dark:text-emerald-400 dark:ring-emerald-500/5' =>
                            $summary['status'] === SystemHealth::OK,
                    ])
                >
                    <x-admin.icon :name="$tone[$summary['status']]['icon']" class="h-6 w-6" />
                </span>
                <div>
                    <h2 class="text-lg font-semibold text-slate-900 dark:text-white">{{ $headline }}</h2>
                    <p class="mt-0.5 text-sm text-slate-600 dark:text-slate-400">
                        {{ $summary['total'] }} checks · checked {{ Carbon::createFromTimestamp($summary['checked_at'])->diffForHumans() }}
                    </p>
                </div>
            </div>

            <dl class="grid grid-cols-3 gap-2 text-center sm:flex sm:gap-3">
                @foreach ([[SystemHealth::OK, $summary['passed'], 'Passed'], [SystemHealth::WARNING, $summary['warnings'], 'Warnings'], [SystemHealth::ERROR, $summary['errors'], 'Problems']] as [$status, $count, $label])
                    <div class="rounded-lg border border-white/60 bg-white/70 px-4 py-2 dark:border-slate-800 dark:bg-slate-900/60">
                        <dd @class(['tabular text-xl font-semibold', $count ? $tone[$status]['text'] : 'text-slate-300 dark:text-slate-600'])>
                            {{ $count }}
                        </dd>
                        <dt class="text-xs text-slate-500 dark:text-slate-400">{{ $label }}</dt>
                    </div>
                @endforeach
            </dl>
        </div>

        @if ($attention->isNotEmpty())
            <ul
                class="divide-y divide-slate-200/70 border-t border-slate-200/70 bg-white/60 dark:divide-slate-800 dark:border-slate-800 dark:bg-slate-900/40"
            >
                @foreach ($attention as $check)
                    <li class="flex items-start gap-3 px-5 py-3">
                        <x-admin.icon
                            :name="$tone[$check['status']]['icon']"
                            @class(['mt-0.5 h-4 w-4 shrink-0', $tone[$check['status']]['text']])
                        />
                        <div class="min-w-0 flex-1 text-sm">
                            <p class="text-slate-800 dark:text-slate-100">
                                <a href="#{{ $check['groupKey'] }}" class="font-medium hover:underline">
                                    {{ $check['group'] }} · {{ $check['label'] }}
                                </a>
                                <span class="text-slate-500 dark:text-slate-400">{{ $check['message'] }}</span>
                            </p>
                            @if ($check['fix'])
                                <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">
                                    <span class="font-medium text-slate-700 dark:text-slate-300">How to fix:</span>
                                    {{ $check['fix'] }}
                                </p>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    <div class="gap-6 xl:columns-2">
        @foreach ($groups as $group)
            <x-admin.card
                :id="$group['key']"
                :title="$group['label']"
                :icon="$group['icon']"
                class="mb-6 scroll-mt-24 break-inside-avoid"
                :padded="false"
            >
                <x-slot:actions>
                    <span
                        @class(['inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset', $tone[$group['status']]['pill']])
                    >
                        <x-admin.icon :name="$tone[$group['status']]['icon']" class="h-3.5 w-3.5" />
                        {{ $tone[$group['status']]['label'] }}
                    </span>
                </x-slot>

                <ul class="divide-y divide-slate-100 dark:divide-slate-800">
                    @foreach ($group['checks'] as $check)
                        <li class="flex items-start gap-3 px-4 py-3 sm:px-5">
                            <x-admin.icon
                                :name="$tone[$check['status']]['icon']"
                                @class(['mt-0.5 h-4 w-4 shrink-0', $tone[$check['status']]['text']])
                            />
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-baseline justify-between gap-x-3 gap-y-0.5">
                                    <p class="text-sm font-medium text-slate-800 dark:text-slate-100">{{ $check['label'] }}</p>
                                    @if ($check['value'])
                                        <p class="tabular max-w-full text-sm wrap-break-word text-slate-600 sm:text-right dark:text-slate-300">
                                            {{ $check['value'] }}
                                        </p>
                                    @endif
                                </div>
                                <p class="mt-0.5 text-xs leading-relaxed text-slate-500 dark:text-slate-400">{{ $check['message'] }}</p>
                                @if ($check['fix'] && in_array($check['status'], [SystemHealth::ERROR, SystemHealth::WARNING], true))
                                    <p
                                        @class(['mt-1 text-xs font-medium', $check['status'] === SystemHealth::ERROR ? 'text-red-700 dark:text-red-300' : 'text-amber-700 dark:text-amber-300'])
                                    >
                                        {{ $check['fix'] }}
                                    </p>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ul>

                @includeIf('admin.system-health.partials.' . $group['key'], ['group' => $group, 'extra' => $group['extra']])
            </x-admin.card>
        @endforeach
    </div>
</x-admin>
