@php
    $logFiles = $logFiles ?? [];
    $canView = auth()
        ->user()
        ->can('admin.log-settings.view');
    $canDelete = auth()
        ->user()
        ->can('admin.log-settings.delete');
    $canManage = $canView || $canDelete;

    $headers = ['File', 'Size', 'Last modified'];
    if ($canManage) {
        $headers[] = 'Actions';
    }
@endphp

<div x-data="logSettings()" x-init="init()" class="space-y-6">
    <x-admin.table
        :headers="$headers"
        :data="$logFiles"
        empty-message="No log files found."
        empty-text="Server log files will show up here when the application writes them."
        empty-icon="scroll"
    >
        <x-slot:toolbar>
            <div class="flex flex-wrap items-center justify-between gap-3 py-1">
                <div class="flex min-w-0 items-center gap-3">
                    <span
                        class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg border border-slate-200 bg-slate-50 text-slate-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300"
                    >
                        <x-admin.icon name="scroll" class="h-4 w-4" />
                    </span>
                    <div class="min-w-0">
                        <h2 class="flex items-center gap-2 text-sm font-semibold text-slate-900 dark:text-white">
                            Application logs
                            <span
                                class="tabular rounded-full border border-slate-200 bg-white px-2 py-px text-xs font-medium text-slate-600 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300"
                            >
                                {{ count($logFiles) }}
                            </span>
                        </h2>
                        <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">View and manage server-side log files.</p>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <x-admin.button variant="secondary" size="sm" icon="refresh" :href="request()->fullUrl()">Refresh</x-admin.button>

                    @can('admin.log-settings.delete')
                        @if (count($logFiles) > 0)
                            <form
                                method="POST"
                                action="{{ route('admin.settings.logs.destroy-all') }}"
                                x-data
                                x-on:submit.prevent="
                                    if (confirm('Delete ALL log files? This cannot be undone.')) $el.submit()
                                "
                            >
                                @csrf
                                @method('DELETE')

                                <x-admin.button variant="danger-outline" size="sm" icon="trash">Delete all</x-admin.button>
                            </form>
                        @endif
                    @endcan
                </div>
            </div>
        </x-slot>

        @foreach ($logFiles as $log)
            @php
                $modifiedAt = isset($log['modified_ts']) ? \Illuminate\Support\Carbon::createFromTimestamp($log['modified_ts']) : null;
            @endphp

            <tr>
                <td>
                    <div class="flex items-center gap-3">
                        <span
                            class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border border-slate-200 bg-slate-50 text-slate-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300"
                        >
                            <x-admin.icon name="file-text" class="h-4 w-4" />
                        </span>
                        <div class="min-w-0">
                            @if ($canView)
                                <button
                                    type="button"
                                    x-on:click="openViewer(@js($log['filename']))"
                                    class="cursor-pointer truncate font-mono text-[13px] font-medium text-slate-900 hover:text-blue-600 focus:outline-none focus-visible:underline dark:text-white dark:hover:text-blue-400"
                                >
                                    {{ $log['filename'] }}
                                </button>
                            @else
                                <p class="truncate font-mono text-[13px] font-medium text-slate-900 dark:text-white">{{ $log['filename'] }}</p>
                            @endif
                            <p class="mt-0.5 flex items-center gap-1 text-xs text-slate-500 dark:text-slate-400">
                                <x-admin.icon name="terminal" class="h-3 w-3" />
                                storage/logs
                            </p>
                        </div>
                    </div>
                </td>

                <td class="tabular text-sm whitespace-nowrap text-slate-600 dark:text-slate-300">
                    {{ $log['size'] }}
                </td>

                <td class="whitespace-nowrap">
                    @if ($modifiedAt)
                        <p class="text-sm text-slate-700 dark:text-slate-300">{{ $modifiedAt->format('d M Y') }}</p>
                        <p class="text-xs text-slate-400">{{ $modifiedAt->diffForHumans() }}</p>
                    @else
                        <p class="text-sm text-slate-700 dark:text-slate-300">{{ $log['modified'] }}</p>
                    @endif
                </td>

                @if ($canManage)
                    <td>
                        <x-admin.row-actions
                            size="sm"
                            :viewClick="'openViewer(\'' . $log['filename'] . '\')'"
                            :canView="auth()->user()->can('admin.log-settings.view')"
                            :deleteFormAction="route('admin.settings.logs.destroy')"
                            :deleteFormField="['name' => 'file', 'value' => $log['filename']]"
                            :deleteConfirmMessage="'Delete ' . $log['filename'] . '? This cannot be undone.'"
                            :canDelete="auth()->user()->can('admin.log-settings.delete')"
                        />
                    </td>
                @endif
            </tr>
        @endforeach
    </x-admin.table>

    <div
        x-show="viewerOpen"
        x-cloak
        x-transition:enter="transition duration-200 ease-out"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition duration-150 ease-in"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/50 p-2 backdrop-blur-sm sm:p-4 dark:bg-black/70"
        x-on:click="closeViewer()"
        x-on:keydown.escape.window="closeViewer()"
    >
        <div
            role="dialog"
            aria-modal="true"
            aria-labelledby="log-viewer-title"
            class="flex h-[90vh] w-full max-w-5xl flex-col overflow-hidden rounded-xl border border-slate-200 bg-white shadow-2xl sm:h-[85vh] dark:border-slate-800 dark:bg-slate-900"
            x-on:click.stop
        >
            <div class="flex shrink-0 items-center justify-between gap-3 border-b border-slate-100 px-4 py-3 sm:px-5 dark:border-slate-800">
                <div class="flex min-w-0 items-center gap-3">
                    <span
                        class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg border border-slate-200 bg-slate-50 text-slate-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300"
                    >
                        <x-admin.icon name="file-text" class="h-4 w-4" />
                    </span>

                    <div class="min-w-0">
                        <p
                            id="log-viewer-title"
                            class="truncate font-mono text-sm font-semibold text-slate-900 dark:text-white"
                            x-text="viewerFilename"
                        ></p>
                        <p class="text-xs text-slate-500 dark:text-slate-400" x-show="logData">
                            <span x-text="logData?.size"></span>
                            ·
                            <span x-text="rawLines.length"></span>
                            lines (last 500)
                        </p>
                    </div>
                </div>
                <div class="flex shrink-0 items-center gap-1">
                    <button
                        type="button"
                        x-on:click="fetchLog()"
                        x-bind:disabled="viewerLoading"
                        title="Refresh"
                        aria-label="Refresh log"
                        class="flex h-8 w-8 cursor-pointer items-center justify-center rounded-lg text-slate-500 transition hover:bg-slate-100 hover:text-slate-900 focus:outline-none focus-visible:ring-3 focus-visible:ring-blue-500/30 disabled:opacity-50 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-white"
                    >
                        <x-admin.icon name="refresh" x-bind:class="viewerLoading ? 'animate-spin' : ''" class="h-4 w-4" />
                    </button>

                    <button
                        type="button"
                        x-on:click="closeViewer()"
                        title="Close"
                        aria-label="Close log viewer"
                        class="flex h-8 w-8 cursor-pointer items-center justify-center rounded-lg text-slate-500 transition hover:bg-slate-100 hover:text-slate-900 focus:outline-none focus-visible:ring-3 focus-visible:ring-blue-500/30 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-white"
                    >
                        <x-admin.icon name="x" class="h-4 w-4" />
                    </button>
                </div>
            </div>

            <div class="shrink-0 border-b border-slate-100 bg-slate-50/70 px-4 py-2.5 sm:px-5 dark:border-slate-800 dark:bg-slate-900/60">
                <x-admin.form.input
                    type="search"
                    id="log-filter"
                    aria-label="Filter log lines"
                    x-model="filter"
                    x-bind:disabled="viewerLoading"
                    placeholder="Filter log lines…"
                >
                    <x-slot:leftIcon>
                        <x-admin.icon name="search" class="h-4 w-4" />
                    </x-slot>
                </x-admin.form.input>
            </div>

            <div class="flex-1 overflow-y-auto bg-white px-3 py-3 sm:px-5 dark:bg-slate-900">
                <template x-if="viewerLoading">
                    <div class="flex h-full items-center justify-center">
                        <x-admin.icon name="refresh" class="h-6 w-6 animate-spin text-blue-500 dark:text-blue-400" />
                    </div>
                </template>

                <template x-if="!viewerLoading && viewerError">
                    <div class="mt-12 flex flex-col items-center gap-2 text-center">
                        <x-admin.icon name="alert-circle" class="h-6 w-6 text-red-500" />
                        <p class="text-sm text-red-600 dark:text-red-400" x-text="viewerError"></p>
                        <button
                            type="button"
                            x-on:click="fetchLog()"
                            class="mt-1 cursor-pointer text-xs font-medium text-blue-600 hover:underline dark:text-blue-400"
                        >
                            Try again
                        </button>
                    </div>
                </template>

                <template x-if="!viewerLoading && !viewerError && filteredEntries.length === 0">
                    <div class="mt-12 flex flex-col items-center gap-2 text-center">
                        <x-admin.icon name="search" class="h-6 w-6 text-slate-300 dark:text-slate-600" />
                        <p class="text-sm text-slate-500 dark:text-slate-400">No matching log entries.</p>
                    </div>
                </template>

                <template x-if="!viewerLoading && !viewerError">
                    <div>
                        <template x-for="(entry, i) in filteredEntries" :key="i">
                            <div class="mb-1 font-mono text-xs">
                                <div
                                    x-bind:class="
                                        [
                                            levelClass(entry.head),
                                            entry.trace.length ? 'cursor-pointer select-none' : '',
                                        ]
                                    "
                                    class="flex items-start gap-2 rounded-md px-2 py-1"
                                    x-on:click="entry.trace.length && toggleTrace(i)"
                                >
                                    <template x-if="entry.trace.length">
                                        <span class="mt-0.5 shrink-0 text-slate-400 dark:text-slate-500">
                                            <x-admin.icon
                                                name="chevron-down"
                                                class="h-3 w-3 transition-transform duration-200"
                                                x-bind:class="{ 'rotate-180': !collapsed.includes(i) }"
                                            />
                                        </span>
                                    </template>
                                    <span class="leading-relaxed break-all whitespace-pre-wrap" x-text="entry.head"></span>
                                </div>

                                <template x-if="entry.trace.length && ! collapsed.includes(i)">
                                    <div class="mt-0.5 ml-5 rounded-md bg-slate-50 px-3 py-2 dark:bg-slate-800/60">
                                        <template x-for="(t, j) in entry.trace" :key="j">
                                            <p
                                                class="leading-relaxed break-all whitespace-pre-wrap text-slate-500 dark:text-slate-400"
                                                x-text="t"
                                            ></p>
                                        </template>
                                    </div>
                                </template>
                            </div>
                        </template>
                    </div>
                </template>
            </div>
        </div>
    </div>
</div>

@push('scripts')
    <script defer>
        function logSettings() {
            return {
                viewerOpen: false,
                viewerFilename: '',
                viewerLoading: false,
                viewerError: null,
                logData: null,
                filter: '',
                collapsed: [],

                get rawLines() {
                    return this.logData ? this.logData.content.split('\n').filter(Boolean) : [];
                },

                get grouped() {
                    const groups = [];
                    this.rawLines.forEach((line) => {
                        const isTrace = line.startsWith('#') || line.startsWith('Stack trace:') || line.startsWith('"');

                        if (isTrace && groups.length > 0) {
                            groups[groups.length - 1].trace.push(line);
                        } else {
                            groups.push({ head: line, trace: [] });
                        }
                    });
                    return groups;
                },

                get filteredEntries() {
                    if (!this.filter) return this.grouped;
                    const q = this.filter.toLowerCase();
                    return this.grouped.filter((g) => g.head.toLowerCase().includes(q));
                },

                init() {},

                openViewer(filename) {
                    this.viewerFilename = filename;
                    this.viewerOpen = true;
                    this.filter = '';
                    this.collapsed = [];
                    this.fetchLog();
                },

                closeViewer() {
                    this.viewerOpen = false;
                    this.viewerFilename = '';
                    this.logData = null;
                    this.viewerError = null;
                },

                fetchLog() {
                    this.viewerLoading = true;
                    this.viewerError = null;

                    axios
                        .get(`{{ route('admin.settings.logs.show') }}`, {
                            params: {
                                file: this.viewerFilename,
                            },
                        })
                        .then((res) => {
                            this.logData = res.data;
                        })
                        .catch((err) => {
                            this.viewerError = err.response?.data?.error || err.message || 'Server error';
                        })
                        .finally(() => {
                            this.viewerLoading = false;
                        });
                },

                toggleTrace(i) {
                    const idx = this.collapsed.indexOf(i);
                    if (idx === -1) {
                        this.collapsed.push(i);
                    } else {
                        this.collapsed.splice(idx, 1);
                    }
                },

                levelClass(line) {
                    const LEVELS = {
                        EMERGENCY: 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300',
                        ALERT: 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300',
                        CRITICAL: 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300',
                        ERROR: 'bg-red-50 text-red-600 dark:bg-red-900/30 dark:text-red-400',
                        WARNING: 'bg-yellow-50 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-300',
                        NOTICE: 'bg-blue-50 text-blue-600 dark:bg-blue-900/30 dark:text-blue-300',
                        INFO: 'bg-green-50 text-green-600 dark:bg-green-900/30 dark:text-green-300',
                        DEBUG: 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300',
                    };
                    const upper = line.toUpperCase();
                    for (const [level, cls] of Object.entries(LEVELS)) {
                        if (upper.includes('.' + level) || upper.includes('[' + level + ']')) return cls;
                    }
                    return 'text-slate-700 dark:text-slate-300';
                },
            };
        }
    </script>
@endpush
