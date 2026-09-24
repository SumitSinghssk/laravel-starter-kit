@if (count($health))
    @php
        $issues = collect($health)
            ->filter(fn ($item) => $item['count'] > 0 && ! ($item['neutral'] ?? false))
            ->count();
    @endphp

    <x-admin.card
        title="Content health"
        text="Small fixes that help SEO and sharing."
        icon="shield-check"
        :padded="false"
        @class(['xl:col-span-3' => ! $chart])
    >
        <x-slot:actions>
            @if ($issues)
                <x-admin.status-badge tone="warning" :label="$issues . ' to fix'" />
            @else
                <x-admin.status-badge tone="success" label="All good" />
            @endif
        </x-slot>

        <ul class="divide-y divide-slate-100 dark:divide-slate-800">
            @foreach ($health as $item)
                @php
                    $ok = $item['count'] === 0;
                    $neutral = $item['neutral'] ?? false;
                @endphp

                <li>
                    <a
                        href="{{ $item['url'] }}"
                        class="group flex items-center justify-between gap-3 px-4 py-3 transition hover:bg-slate-50 sm:px-5 dark:hover:bg-slate-800/40"
                    >
                        <span class="flex min-w-0 items-center gap-2.5 text-sm text-slate-700 dark:text-slate-300">
                            <x-admin.icon
                                :name="$ok ? 'check-circle' : ($neutral ? 'circle-dot' : 'alert-circle')"
                                @class([
                                    'h-4.5 w-4.5',
                                    'text-emerald-500' => $ok,
                                    'text-slate-400' => ! $ok && $neutral,
                                    'text-amber-500' => ! $ok && ! $neutral,
                                ])
                            />
                            <span class="truncate">{{ $item['label'] }}</span>
                        </span>
                        <span class="tabular flex items-center gap-1 text-sm font-semibold text-slate-900 dark:text-white">
                            @if ($item['boolean'] ?? false)
                                {{ $ok ? 'Yes' : 'No' }}
                            @else
                                {{ $item['count'] }}
                            @endif
                            <x-admin.icon
                                name="chevron-right"
                                class="h-3.5 w-3.5 text-slate-300 transition group-hover:text-slate-500 dark:text-slate-600"
                            />
                        </span>
                    </a>
                </li>
            @endforeach
        </ul>
    </x-admin.card>
@endif
