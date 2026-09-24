@if ($chart)
    <div class="rounded-xl border border-slate-200/80 bg-white p-4 shadow-xs sm:p-5 xl:col-span-2 dark:border-slate-800 dark:bg-slate-900">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="flex items-start gap-3">
                <span
                    class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg border border-slate-200 bg-slate-50 text-slate-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300"
                >
                    <x-admin.icon name="bar-chart" class="h-4 w-4" />
                </span>
                <div>
                    <h2 class="text-sm font-semibold text-slate-900 dark:text-white">Enquiries · last 14 days</h2>
                    <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">
                        @if ($chart['busiest'])
                            Busiest day: {{ $chart['busiest']['label'] }} ({{ $chart['busiest']['count'] }})
                        @else
                            New contact form messages will appear here.
                        @endif
                    </p>
                </div>
            </div>
            <div class="text-right">
                <p class="text-2xl font-semibold tracking-tight text-slate-900 tabular-nums dark:text-white">{{ number_format($chart['total']) }}</p>

                @if (! is_null($chart['delta']))
                    <p
                        class="{{ $chart['delta'] >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400' }} text-[11px] font-semibold"
                    >
                        {{ $chart['delta'] >= 0 ? '+' : '−' }}{{ abs($chart['delta']) }}% vs previous 14 days
                    </p>
                @else
                    <p class="text-[11px] text-slate-400">total enquiries</p>
                @endif
            </div>
        </div>

        <div x-data="{ hover: null }" class="relative mt-6 flex h-56 gap-2">
            <div class="flex w-6 shrink-0 flex-col justify-between pb-5 text-right text-[10px] text-slate-400 tabular-nums" aria-hidden="true">
                @foreach ($ticks as $tick)
                    <span class="-translate-y-1/2 leading-none">{{ $tick }}</span>
                @endforeach
            </div>

            <div class="relative flex-1">
                <div class="pointer-events-none absolute inset-x-0 top-0 bottom-5 flex flex-col justify-between" aria-hidden="true">
                    @foreach ($ticks as $tick)
                        <div class="h-px bg-slate-100 dark:bg-slate-800"></div>
                    @endforeach
                </div>

                <div class="absolute inset-0 flex items-stretch gap-0.5" aria-hidden="true">
                    @foreach ($chart['days'] as $i => $day)
                        <div
                            class="flex flex-1 cursor-default flex-col items-center"
                            x-on:mouseenter="hover = {{ $i }}"
                            x-on:mouseleave="hover = null"
                        >
                            <div
                                class="relative flex w-full flex-1 items-end justify-center rounded-md transition-colors"
                                :class="hover === {{ $i }} && 'bg-slate-50 dark:bg-slate-800/50'"
                            >
                                <div
                                    class="chart-bar w-full max-w-6 rounded-t-[4px] transition-opacity"
                                    :class="hover !== null && hover !== {{ $i }} && 'opacity-40'"
                                    style="height: {{ $day['count'] ? max(2, ($day['count'] / $yMax) * 100) : 0 }}%"
                                ></div>

                                <div
                                    x-show="hover === {{ $i }}"
                                    x-cloak
                                    class="pointer-events-none absolute bottom-full z-10 mb-1.5 rounded-lg bg-slate-900 px-2 py-1 text-center text-[11px] whitespace-nowrap text-white shadow-lg dark:bg-slate-700"
                                >
                                    <span class="block text-slate-300">{{ $day['weekday'] }}, {{ $day['label'] }}</span>
                                    <span class="font-semibold">{{ $day['count'] }} {{ Str::plural('enquiry', $day['count']) }}</span>
                                </div>
                            </div>
                            <span
                                class="{{ $loop->last ? 'font-semibold text-slate-700 dark:text-slate-200' : 'text-slate-400' }} mt-1 h-4 text-[10px] tabular-nums"
                            >
                                {{ $loop->first || $loop->last || $i % 3 === 0 ? $day['short'] : '' }}
                            </span>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <table class="sr-only">
            <caption>Enquiries per day, last 14 days</caption>
            <thead>
                <tr>
                    <th scope="col">Date</th>
                    <th scope="col">Enquiries</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($chart['days'] as $day)
                    <tr>
                        <td>{{ $day['label'] }}</td>
                        <td>{{ $day['count'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
