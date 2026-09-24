@php
    use Illuminate\Support\Js;

    $counts = $import->counts ?? [];
    $done = $import->status === \App\Models\Import::COMPLETED;
    $new = $counts['new'] ?? 0;
    $skipped = ($counts['exists'] ?? 0) + ($counts['duplicate'] ?? 0);
    $unknown = $counts['unknown_columns'] ?? [];
    $label = strtolower($type->label());
    $showUrl = fn ($show = null) => route('admin.transfer.imports.show', array_filter(['import' => $import, 'show' => $show]));

    $statuses = [
        'new' => ['info', 'Will be imported'],
        'exists' => ['neutral', 'Already exists'],
        'duplicate' => ['neutral', 'Repeated in file'],
        'invalid' => ['danger', 'Has errors'],
        'created' => ['success', 'Imported'],
        'failed' => ['danger', 'Failed'],
    ];

    $tiles = $done
        ? [
            ['Imported', $counts['created'] ?? 0, 'check-circle', 'text-emerald-600 dark:text-emerald-400', 'created'],
            ['Skipped: already there', $skipped, 'circle-dashed', 'text-slate-500 dark:text-slate-400', 'exists'],
            ['Had errors', $counts['invalid'] ?? 0, 'alert-circle', 'text-red-600 dark:text-red-400', 'invalid'],
            ['Failed', $counts['failed'] ?? 0, 'x-circle', 'text-red-600 dark:text-red-400', 'failed'],
        ]
        : [
            ['New: will be imported', $new, 'plus', 'text-blue-600 dark:text-blue-400', 'new'],
            ['Already exist: skipped', $counts['exists'] ?? 0, 'circle-dashed', 'text-slate-500 dark:text-slate-400', 'exists'],
            ['Repeated in file: skipped', $counts['duplicate'] ?? 0, 'copy', 'text-slate-500 dark:text-slate-400', 'duplicate'],
            ['Errors: skipped', $counts['invalid'] ?? 0, 'alert-circle', 'text-red-600 dark:text-red-400', 'invalid'],
        ];

    $tabs = [['label' => 'All rows', 'url' => $showUrl(), 'count' => $import->total, 'active' => ! $filter]];
    foreach ($statuses as $key => [, $name]) {
        if (($counts[$key] ?? 0) > 0) {
            $tabs[] = ['label' => $name, 'url' => $showUrl($key), 'count' => $counts[$key], 'active' => $filter === $key];
        }
    }
    if ($filter === 'warnings' || collect($import->rows())->contains(fn ($row) => $row['warnings'])) {
        $tabs[] = ['label' => 'With warnings', 'url' => $showUrl('warnings'), 'active' => $filter === 'warnings'];
    }
@endphp

<x-admin
    :breadcrumb="[
        ['label' => $type->label(), 'url' => $type->listUrl()],
        ['label' => 'Import', 'url' => route('admin.transfer.create', $type->key())],
        ['label' => $done ? 'Results' : 'Preview'],
    ]"
>
    <x-admin.page-header
        :title="$done ? 'Import finished' : 'Check before importing'"
        :description="$import->original_name . ' · ' . number_format($import->total) . ' ' . ($import->total === 1 ? 'row' : 'rows')"
        :back="route('admin.transfer.create', $type->key())"
    >
        <x-slot:actions>
            @if ($done)
                <x-admin.button variant="secondary" icon="upload" :href="route('admin.transfer.create', $type->key())">
                    Import another file
                </x-admin.button>
                <x-admin.button icon="arrow-right" :href="$type->listUrl()">Go to {{ $label }}</x-admin.button>
            @else
                <x-admin.confirm-button
                    :action="route('admin.transfer.imports.destroy', $import)"
                    method="DELETE"
                    title="Discard this import?"
                    message="Nothing from this file will be imported."
                    confirm="Discard"
                    variant="secondary"
                    icon="x"
                >
                    Discard
                </x-admin.confirm-button>
            @endif
        </x-slot>
    </x-admin.page-header>

    <div class="mb-6 grid grid-cols-2 gap-3 lg:grid-cols-4">
        @foreach ($tiles as [$name, $value, $icon, $color, $show])
            <a
                href="{{ $showUrl($show) }}"
                class="group rounded-xl border border-slate-200/80 bg-white p-4 shadow-xs transition hover:border-slate-300 dark:border-slate-800 dark:bg-slate-900 dark:hover:border-slate-700"
            >
                <span class="flex items-center gap-2 text-xs font-medium text-slate-500 dark:text-slate-400">
                    <x-admin.icon :name="$icon" class="h-4 w-4 {{ $color }}" />
                    {{ $name }}
                </span>
                <span class="tabular mt-1.5 block text-2xl font-semibold tracking-tight text-slate-900 dark:text-white">
                    {{ number_format($value) }}
                </span>
            </a>
        @endforeach
    </div>

    @if ($unknown)
        <div
            class="mb-4 flex items-start gap-2.5 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-200"
        >
            <x-admin.icon name="alert-triangle" class="mt-0.5 h-4 w-4 shrink-0" />
            <span>
                These columns are not recognised and will be ignored:
                <strong>{{ implode(', ', $unknown) }}</strong>
                .
            </span>
        </div>
    @endif

    @unless ($done)
        <div
            class="mb-6 rounded-xl border border-blue-200 bg-blue-50/60 p-4 dark:border-blue-500/30 dark:bg-blue-500/10"
            x-data="{
                running: {{ Js::from($import->status === \App\Models\Import::RUNNING) }},
                percent: {{ Js::from($import->percent()) }},
                created: {{ Js::from($counts['created'] ?? 0) }},
                failed: {{ Js::from($counts['failed'] ?? 0) }},
                error: '',
                async run() {
                    this.running = true
                    this.error = ''
                    try {
                        while (true) {
                            const response = await fetch(
                                {{ Js::from(route('admin.transfer.imports.step', $import)) }},
                                {
                                    method: 'POST',
                                    headers: {
                                        'X-CSRF-TOKEN': {{ Js::from(csrf_token()) }},
                                        Accept: 'application/json',
                                    },
                                },
                            )
                            if (! response.ok)
                                throw new Error(
                                    'The server answered ' + response.status + '.',
                                )
                            const data = await response.json()
                            this.percent = data.percent
                            this.created = data.created
                            this.failed = data.failed
                            if (data.status === 'completed') break
                        }
                        window.location = {{ Js::from($showUrl()) }}
                    } catch (e) {
                        this.running = false
                        this.error =
                            e.message +
                            ' Rows imported so far are kept; press the button again to continue.'
                    }
                },
            }"
            x-init="if (running) run()"
        >
            @if ($new > 0)
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div class="text-sm text-slate-700 dark:text-slate-200">
                        <p class="font-semibold text-slate-900 dark:text-white" x-show="! running">
                            {{ number_format($new) }} new {{ $new === 1 ? $type->singular() : $label }} will be imported.
                        </p>
                        <p class="font-semibold text-slate-900 dark:text-white" x-show="running" x-cloak>
                            Importing…
                            <span class="tabular" x-text="percent + '%'"></span>
                        </p>
                        <p class="mt-0.5 text-slate-600 dark:text-slate-300" x-show="! running">
                            @if ($skipped || ($counts['invalid'] ?? 0))
                                The other {{ number_format($import->total - $new) }} rows are skipped: nothing that already exists is changed.
                            @else
                                Images in the file are downloaded and saved on this site.
                            @endif
                        </p>
                        <p class="mt-0.5 text-slate-600 dark:text-slate-300" x-show="running" x-cloak>
                            <span class="tabular" x-text="created"></span>
                            imported
                            <span x-show="failed">
                                ·
                                <span class="tabular" x-text="failed"></span>
                                failed
                            </span>
                            . Keep this page open.
                        </p>
                    </div>
                    <x-admin.button type="button" icon="download" x-on:click="run()" x-bind:disabled="running" class="shrink-0">
                        Import {{ number_format($new) }} {{ $new === 1 ? 'row' : 'rows' }}
                    </x-admin.button>
                </div>

                <div class="mt-3 h-1.5 overflow-hidden rounded-full bg-blue-100 dark:bg-blue-500/20" x-show="running" x-cloak>
                    <div
                        class="h-full rounded-full bg-blue-600 transition-all duration-300 dark:bg-blue-400"
                        x-bind:style="'width: ' + percent + '%'"
                    ></div>
                </div>

                <p class="mt-3 flex items-start gap-1.5 text-sm font-medium text-red-600 dark:text-red-400" x-show="error" x-cloak>
                    <x-admin.icon name="alert-circle" class="mt-0.5 h-4 w-4 shrink-0" />
                    <span x-text="error"></span>
                </p>
            @else
                <p class="flex items-start gap-2 text-sm text-slate-700 dark:text-slate-200">
                    <x-admin.icon name="info" class="mt-0.5 h-4 w-4 shrink-0 text-blue-600 dark:text-blue-400" />
                    <span>
                        There is nothing new to import: every row already exists, is repeated or has errors. Fix the file and upload it again.
                    </span>
                </p>
            @endif
        </div>
    @endunless

    <x-admin.tabs label="Rows" :tabs="$tabs" />

    <x-admin.card :padded="false">
        @if ($rows->isEmpty())
            <p class="px-4 py-10 text-center text-sm text-slate-500 dark:text-slate-400">No rows here.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-slate-100 text-xs text-slate-500 dark:border-slate-800 dark:text-slate-400">
                        <tr>
                            <th class="w-16 px-4 py-2.5 font-medium">Row</th>
                            <th class="px-4 py-2.5 font-medium">{{ ucfirst($type->singular()) }}</th>
                            <th class="px-4 py-2.5 font-medium">Status</th>
                            <th class="px-4 py-2.5 font-medium">Notes</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @foreach ($rows as $row)
                            @php
                                [$tone, $statusLabel] = $statuses[$row['status']];
                            @endphp

                            <tr class="align-top" x-data="{ open: false }">
                                <td class="tabular px-4 py-3 text-slate-500 dark:text-slate-400">{{ $row['line'] }}</td>
                                <td class="max-w-md px-4 py-3">
                                    @if (! empty($row['result']['url']))
                                        <a href="{{ $row['result']['url'] }}" class="font-medium text-blue-600 hover:underline dark:text-blue-400">
                                            {{ $row['label'] ?: '(no title)' }}
                                        </a>
                                    @else
                                        <span class="font-medium text-slate-900 dark:text-white">{{ $row['label'] ?: '(no title)' }}</span>
                                    @endif
                                    <button
                                        type="button"
                                        class="ml-1.5 text-xs text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white"
                                        x-on:click="open = ! open"
                                        x-text="open ? 'Hide data' : 'Show data'"
                                    >
                                        Show data
                                    </button>
                                    <dl
                                        class="mt-2 grid grid-cols-[max-content_minmax(0,1fr)] gap-x-3 gap-y-1 rounded-lg bg-slate-50 p-3 text-xs dark:bg-slate-800/60"
                                        x-show="open"
                                        x-cloak
                                    >
                                        @foreach ($row['data'] as $key => $value)
                                            @continue($value === '' || $value === null || $value === [])
                                            <dt class="font-mono text-slate-500 dark:text-slate-400">{{ $key }}</dt>
                                            <dd class="break-words text-slate-800 dark:text-slate-200">
                                                {{ \Illuminate\Support\Str::limit(is_scalar($value) ? (string) $value : json_encode($value), 300) }}
                                            </dd>
                                        @endforeach
                                    </dl>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap"><x-admin.status-badge :tone="$tone" :label="$statusLabel" /></td>
                                <td class="px-4 py-3 text-slate-600 dark:text-slate-300">
                                    @foreach ($row['messages'] as $message)
                                        <p @class(['text-red-600 dark:text-red-400' => in_array($row['status'], ['invalid', 'failed'], true)])>
                                            {{ $message }}
                                        </p>
                                    @endforeach

                                    @foreach ($row['warnings'] as $warning)
                                        <p class="flex items-start gap-1.5 text-amber-700 dark:text-amber-300">
                                            <x-admin.icon name="alert-triangle" class="mt-0.5 h-3.5 w-3.5 shrink-0" />
                                            <span>{{ $warning }}</span>
                                        </p>
                                    @endforeach

                                    @if (! $row['messages'] && ! $row['warnings'])
                                        <span class="text-slate-400 dark:text-slate-500">-</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($rows->hasPages())
                <div class="border-t border-slate-100 px-4 py-3 dark:border-slate-800">{{ $rows->links('vendor.pagination.tailwind') }}</div>
            @endif
        @endif
    </x-admin.card>
</x-admin>
