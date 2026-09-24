@php
    $user = auth('web')->user();

    $tones = [
        'blue' => 'bg-blue-50 text-blue-600 dark:bg-blue-500/10 dark:text-blue-300',
        'violet' => 'bg-violet-50 text-violet-600 dark:bg-violet-500/10 dark:text-violet-300',
        'emerald' => 'bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-300',
        'amber' => 'bg-amber-50 text-amber-600 dark:bg-amber-500/10 dark:text-amber-300',
    ];

    $enquiryStatus = [
        'new' => 'bg-blue-50 text-blue-700 dark:bg-blue-500/10 dark:text-blue-300',
        'seen' => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300',
        'pending' => 'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-300',
        'closed' => 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400',
    ];

    if ($chart) {
        $step = max(1, (int) ceil($chart['max'] / 4));
        $niceStep = collect([1, 2, 5, 10, 20, 25, 50, 100, 250, 500, 1000])->first(fn ($s) => $s >= $step) ?? $step;
        $yMax = $niceStep * (int) ceil($chart['max'] / $niceStep);
        $ticks = range($yMax, 0, $niceStep);
    }
@endphp

<x-admin :breadcrumb="[]">
    <div class="space-y-6">
        @include('admin.dashboard.partials.greeting')

        @include('admin.dashboard.partials.stats')

        <div class="grid grid-cols-1 items-start gap-6 xl:grid-cols-3">
            @include('admin.dashboard.partials.enquiries-chart')

            @include('admin.dashboard.partials.content-health')
        </div>

        <div class="grid grid-cols-1 items-start gap-6 xl:grid-cols-3">
            @include('admin.dashboard.partials.recent-enquiries')

            @include('admin.dashboard.partials.recent-activity')
        </div>

        @include('admin.dashboard.partials.latest-posts')
    </div>
</x-admin>
