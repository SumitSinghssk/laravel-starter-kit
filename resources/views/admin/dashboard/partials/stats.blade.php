@if (count($stats))
    @php
        $columns = [1 => 'lg:grid-cols-1', 2 => 'lg:grid-cols-2', 3 => 'lg:grid-cols-3', 4 => 'lg:grid-cols-4', 5 => 'lg:grid-cols-5'][min(count($stats), 5)];
    @endphp

    <div
        class="{{ $columns }} grid grid-cols-2 gap-px overflow-hidden rounded-xl border border-slate-200/80 bg-slate-100 shadow-xs dark:border-slate-800 dark:bg-slate-800"
    >
        @foreach ($stats as $stat)
            <a
                href="{{ $stat['url'] }}"
                @class([
                    'group relative bg-white p-4 transition-colors hover:bg-slate-50 sm:p-5 dark:bg-slate-900 dark:hover:bg-slate-800/60',
                    'col-span-2 lg:col-span-1' => $loop->last && count($stats) % 2 === 1,
                ])
            >
                <div class="flex items-center justify-between gap-2">
                    <p class="text-[13px] font-medium text-slate-500 dark:text-slate-400">{{ $stat['label'] }}</p>
                    <span class="{{ $tones[$stat['tone']] }} flex h-8 w-8 items-center justify-center rounded-lg">
                        <x-admin.icon :name="$stat['icon']" class="h-4 w-4" />
                    </span>
                </div>

                <div class="mt-2 flex items-end gap-2">
                    <p class="tabular text-3xl font-semibold tracking-tight text-slate-900 dark:text-white">{{ number_format($stat['value']) }}</p>
                    @if (! is_null($stat['delta']))
                        <span
                            @class([
                                'mb-1 inline-flex items-center gap-0.5 rounded-full px-1.5 py-0.5 text-[11px] font-semibold',
                                'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300' => $stat['delta'] >= 0,
                                'bg-red-50 text-red-600 dark:bg-red-500/10 dark:text-red-300' => $stat['delta'] < 0,
                            ])
                        >
                            <x-admin.icon :name="$stat['delta'] >= 0 ? 'trending-up' : 'trending-down'" class="h-3 w-3" stroke="2.2" />
                            {{ abs($stat['delta']) }}%
                        </span>
                    @endif
                </div>

                <p class="mt-1 flex items-center gap-1 text-xs text-slate-400">
                    {{ $stat['hint'] }}
                    <x-admin.icon name="arrow-right" class="h-3 w-3 opacity-0 transition group-hover:translate-x-0.5 group-hover:opacity-100" />
                </p>
            </a>
        @endforeach
    </div>
@endif
