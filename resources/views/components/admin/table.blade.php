@props([
    'headers' => [],
    'data',
    'emptyMessage' => 'No records found.',
    'emptyText' => null,
    'emptyIcon' => 'inbox',
    'wrapperClass' => '',
])

@php
    $filtered = collect(request()->except(['page', 'per_page']))
        ->filter(fn ($v) => filled($v))
        ->isNotEmpty();
    $emptyText ??= $filtered ? 'Nothing matches these filters. Try changing or clearing them.' : 'Records you add will show up here.';
@endphp

<div
    {{ $attributes->class(['admin-table rounded-xl border border-slate-200/80 bg-white shadow-xs dark:border-slate-800 dark:bg-slate-900', 'overflow-hidden' => ! isset($toolbar)]) }}
>
    @isset($toolbar)
        <div class="border-b border-slate-100 px-3 py-2.5 sm:px-4 dark:border-slate-800">
            {{ $toolbar }}
        </div>
    @endisset

    @if (count($data) > 0)
        <div class="{{ $wrapperClass }} overflow-x-auto rounded-b-xl">
            <table class="min-w-full border-separate border-spacing-0">
                <thead>
                    <tr>
                        @foreach ($headers as $header)
                            @if (is_array($header) && ($header['select'] ?? false))
                                <th
                                    scope="col"
                                    class="w-10 border-b border-slate-100 bg-slate-50/70 py-2.5 pr-0 pl-4 dark:border-slate-800 dark:bg-slate-900/60"
                                >
                                    <input
                                        type="checkbox"
                                        x-bind:checked="allSelected"
                                        x-effect="$el.indeterminate = someSelected"
                                        x-on:change="toggleAll()"
                                        class="h-4 w-4 cursor-pointer rounded border-slate-300 text-blue-600 focus:ring-blue-500/30 dark:border-slate-600 dark:bg-slate-800"
                                        aria-label="Select all on this page"
                                    />
                                </th>
                                @continue
                            @endif

                            @php
                                $label = is_array($header) ? $header['label'] ?? '' : $header;
                                $class = is_array($header) ? $header['class'] ?? '' : '';
                            @endphp

                            <th
                                scope="col"
                                @class([
                                    'border-b border-slate-100 bg-slate-50/70 px-4 py-2.5 text-left text-xs font-medium whitespace-nowrap text-slate-500 dark:border-slate-800 dark:bg-slate-900/60 dark:text-slate-400',
                                    'text-right' => $label === 'Actions',
                                    $class,
                                ])
                            >
                                {{ $label }}
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody
                    class="divide-y divide-slate-100 dark:divide-slate-800/80 [&_tr:has([data-bulk-row]:checked)]:bg-blue-50/60 dark:[&_tr:has([data-bulk-row]:checked)]:bg-blue-500/5"
                >
                    {{ $slot }}
                </tbody>
            </table>
        </div>

        @if (is_object($data) && method_exists($data, 'links'))
            <div class="border-t border-slate-100 px-4 py-3 dark:border-slate-800">
                {{ $data->links('vendor.pagination.tailwind') }}
            </div>
        @endif
    @else
        <div class="flex flex-col items-center justify-center px-4 py-16 text-center">
            <div class="relative mb-4">
                <div
                    class="flex h-12 w-12 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-400 shadow-xs dark:border-slate-700 dark:bg-slate-800"
                >
                    <x-admin.icon :name="$filtered ? 'search' : $emptyIcon" class="h-5 w-5" />
                </div>
            </div>
            <h3 class="text-sm font-semibold text-slate-900 dark:text-white">{{ $emptyMessage }}</h3>
            <p class="mt-1 max-w-sm text-sm text-slate-500 dark:text-slate-400">{{ $emptyText }}</p>
            @if ($filtered)
                <a
                    href="{{ url()->current() }}"
                    class="mt-4 inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 shadow-xs transition hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700"
                >
                    <x-admin.icon name="x" class="h-3.5 w-3.5" />
                    Clear filters
                </a>
            @elseif (isset($emptyAction))
                <div class="mt-4">{{ $emptyAction }}</div>
            @endif
        </div>
    @endif
</div>
