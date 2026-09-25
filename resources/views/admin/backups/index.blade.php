@php
    use App\Models\Backup;
    use App\Services\Backup\BackupSchedule;
    use Illuminate\Support\Number;

    $user = auth()->user();
    $canCreate = $user->can('admin.backups.create');
    $canSettings = $user->can('admin.backups.settings');
    $canDownload = $user->can('admin.backups.download');
    $canDelete = $user->can('admin.backups.delete');

    $schedulerOk = $schedulerSeen && $schedulerSeen->gt(now()->subMinutes(5));
    $isWindows = PHP_OS_FAMILY === 'Windows';
    $php = \App\Services\Health\SystemHealth::phpBinary();
    $artisan = base_path('artisan');
    $setupCommand = $isWindows
        ? 'schtasks /Create /F /SC MINUTE /MO 1 /TN "Laravel scheduler" /TR "\"' . $php . '\" \"' . $artisan . '\" schedule:run"'
        : '* * * * * cd ' . base_path() . ' && ' . $php . ' artisan schedule:run >> /dev/null 2>&1';

    $headers = ['Backup', 'Contains', 'Size', 'Status'];
    if ($canDownload || $canDelete) {
        $headers[] = 'Actions';
    }
@endphp

<x-admin :breadcrumb="[['label' => 'Backups', 'url' => route('admin.backups.index')]]">
    <x-admin.page-header
        title="Backups"
        description="Copies of your database and uploaded files, made on a schedule or on demand."
        icon="save"
        :count="$backups->total()"
    />

    <div class="mb-6 grid grid-cols-1 items-start gap-6 xl:grid-cols-[minmax(0,1fr)_22rem]">
        <x-admin.card title="Automatic backups" :text="$scheduleText . '.'" icon="calendar">
            <form
                method="POST"
                action="{{ route('admin.backups.settings') }}"
                x-data="{ enabled: @js((bool) old('enabled', $settings['enabled'])), frequency: @js(old('frequency', $settings['frequency'])) }"
                class="space-y-5"
            >
                @csrf

                <fieldset @disabled(! $canSettings) class="space-y-5">
                    <x-admin.form.toggle
                        name="enabled"
                        label="Make backups automatically"
                        hint="Runs in the background at the time you choose. Needs the scheduler (see the status on the right)."
                        :checked="(bool) $settings['enabled']"
                        x-model="enabled"
                    />

                    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4" x-bind:class="! enabled && 'opacity-50'">
                        <x-admin.form.select
                            name="frequency"
                            label="How often"
                            x-model="frequency"
                            :options="['daily' => 'Every day', 'weekly' => 'Every week', 'monthly' => 'Every month']"
                            :value="$settings['frequency']"
                        />

                        <div x-show="frequency === 'weekly'" x-cloak>
                            <x-admin.form.select
                                name="weekday"
                                label="On"
                                :options="BackupSchedule::WEEKDAYS"
                                :value="(string) $settings['weekday']"
                            />
                        </div>

                        <div x-show="frequency === 'monthly'" x-cloak>
                            <x-admin.form.select
                                name="monthday"
                                label="On day"
                                :options="collect(range(1, 28))->mapWithKeys(fn ($day) => [$day => (string) $day])->all()"
                                :value="(string) $settings['monthday']"
                                hint="1–28, so it happens every month."
                            />
                        </div>

                        <x-admin.form.time-picker
                            name="time"
                            label="At"
                            :value="$settings['time']"
                            :minute-step="5"
                            :hint="'Server time (' . config('app.timezone') . ')'"
                            required
                        />

                        <x-admin.form.input
                            type="number"
                            name="keep"
                            label="Keep the last"
                            min="1"
                            max="60"
                            :value="$settings['keep']"
                            hint="Older backups are deleted."
                            required
                        />
                    </div>

                    <div
                        class="flex flex-wrap gap-x-8 gap-y-3 border-t border-slate-100 pt-4 dark:border-slate-800"
                        x-bind:class="! enabled && 'opacity-50'"
                    >
                        <x-admin.form.toggle
                            name="include_database"
                            label="Database"
                            hint="All content, users and settings."
                            :checked="(bool) $settings['include_database']"
                        />
                        <x-admin.form.toggle
                            name="include_files"
                            label="Uploaded files"
                            hint="Images, videos and documents in storage."
                            :checked="(bool) $settings['include_files']"
                        />
                    </div>
                    <x-admin.form.error for="include_database" />
                </fieldset>

                @if ($canSettings)
                    <div class="flex items-center justify-between gap-3 border-t border-slate-100 pt-4 dark:border-slate-800">
                        <p class="text-xs text-slate-500 dark:text-slate-400">
                            @if ($nextRun)
                                Next automatic backup:
                                <strong class="font-semibold text-slate-700 dark:text-slate-200">
                                    {{ \App\Support\LocalTime::toLocal($nextRun)->format('D') . ' ' . local_datetime($nextRun) }}
                                </strong>
                                ({{ $nextRun->diffForHumans() }})
                            @else
                                Automatic backups are off.
                            @endif
                        </p>
                        <x-admin.button icon="check">Save schedule</x-admin.button>
                    </div>
                @endif
            </form>
        </x-admin.card>

        <aside class="space-y-6">
            @if ($canCreate)
                <x-admin.card
                    title="Back up now"
                    text="Made in small steps; keep this page open until it's done."
                    icon="save"
                    x-data="{
                        includeDatabase: true,
                        includeFiles: true,
                        job: {{ \Illuminate\Support\Js::from($running?->progressPayload()) }},
                        error: '',
                        busy: false,
                        async start() {
                            if (this.busy) return
                            this.busy = true
                            this.error = ''
                            try {
                                const { data } = await axios.post({{ \Illuminate\Support\Js::from(route('admin.backups.store')) }}, { include_database: this.includeDatabase, include_files: this.includeFiles })
                                this.job = data.backup
                                await this.drive()
                            } catch (e) {
                                this.error = e?.response?.data?.errors ? Object.values(e.response.data.errors).flat()[0] : 'The backup could not be started.'
                            } finally {
                                this.busy = false
                            }
                        },
                        async drive() {
                            let failures = 0
                            while (this.job && this.job.status === 'running') {
                                const before = this.job.percent
                                try {
                                    const { data } = await axios.post(this.job.step_url)
                                    this.job = data.backup
                                    failures = 0
                                    if (this.job.status === 'running' && this.job.percent === before) await new Promise((r) => setTimeout(r, 1500))
                                } catch (e) {
                                    if (++failures > 4) {
                                        this.error = 'Lost contact with the server. The backup is saved so far; press Resume to continue.'
                                        return
                                    }
                                    await new Promise((r) => setTimeout(r, 2000 * failures))
                                }
                            }
                            if (this.job?.status === 'completed') {
                                window.toast?.('success', 'Backup completed (' + this.job.size + ').')
                                setTimeout(() => window.location.reload(), 900)
                            }
                        },
                        init() {
                            if (this.job?.status === 'running') this.drive()
                        },
                    }"
                >
                    <div class="space-y-4">
                        <template x-if="! job || job.status !== 'running'">
                            <div class="space-y-4">
                                <div class="space-y-2">
                                    <x-admin.form.checkbox id="backup-now-database" label="Database" x-model="includeDatabase" />
                                    <x-admin.form.checkbox id="backup-now-files" label="Uploaded files" x-model="includeFiles" />
                                </div>
                                <x-admin.button
                                    type="button"
                                    icon="save"
                                    full
                                    x-on:click="start()"
                                    x-bind:disabled="busy || (! includeDatabase && ! includeFiles)"
                                >
                                    <span x-text="busy ? 'Starting…' : 'Back up now'">Back up now</span>
                                </x-admin.button>
                            </div>
                        </template>

                        <template x-if="job && job.status === 'running'">
                            <div>
                                <div class="flex items-baseline justify-between gap-3 text-sm">
                                    <span class="font-medium text-slate-800 dark:text-slate-100">Backing up…</span>
                                    <span class="tabular text-xs text-slate-500" x-text="job.percent + '%'"></span>
                                </div>
                                <div class="mt-2 h-2 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800">
                                    <div
                                        class="h-full rounded-full bg-blue-600 transition-[width] duration-500 dark:bg-blue-500"
                                        x-bind:style="`width: ${job.percent}%`"
                                    ></div>
                                </div>
                                <p class="mt-2 truncate text-xs text-slate-500 dark:text-slate-400" x-text="job.status_line"></p>
                                <x-admin.button
                                    type="button"
                                    size="sm"
                                    variant="secondary"
                                    icon="play"
                                    class="mt-3"
                                    x-show="error"
                                    x-on:click="error = ''; drive()"
                                >
                                    Resume
                                </x-admin.button>
                            </div>
                        </template>

                        <p
                            x-show="error"
                            x-cloak
                            x-text="error"
                            class="rounded-lg bg-red-50 px-3 py-2 text-xs text-red-600 dark:bg-red-500/10 dark:text-red-300"
                        ></p>
                        <p
                            x-show="job && job.status === 'failed'"
                            x-cloak
                            class="rounded-lg bg-red-50 px-3 py-2 text-xs text-red-600 dark:bg-red-500/10 dark:text-red-300"
                            x-text="'Backup failed: ' + (job?.error ?? '')"
                        ></p>
                    </div>
                </x-admin.card>
            @endif

            <x-admin.card title="Scheduler" icon="clock">
                @if ($schedulerOk)
                    <p class="flex items-start gap-2 text-sm text-emerald-700 dark:text-emerald-400">
                        <x-admin.icon name="check-circle" class="mt-0.5 h-4 w-4 shrink-0" />
                        <span>Running. Last check {{ $schedulerSeen->diffForHumans() }}.</span>
                    </p>
                @else
                    <div class="space-y-3 text-sm">
                        <p class="flex items-start gap-2 text-amber-700 dark:text-amber-400">
                            <x-admin.icon name="alert-triangle" class="mt-0.5 h-4 w-4 shrink-0" />
                            <span>
                                {{ $schedulerSeen ? 'Not seen since ' . $schedulerSeen->diffForHumans() . '.' : 'Not set up on this server yet.' }}
                                Automatic backups only start while it runs. “Back up now” always works.
                            </span>
                        </p>
                        <p class="text-xs text-slate-500 dark:text-slate-400">
                            {{ $isWindows ? 'Run this once in a Command Prompt opened as administrator:' : 'Add this line to the server\'s crontab (crontab -e):' }}
                        </p>
                        <div x-data="{ copied: false }" class="relative">
                            <code
                                class="block rounded-lg bg-slate-100 p-2.5 pr-9 font-mono text-[11px] break-all text-slate-700 dark:bg-slate-800 dark:text-slate-300"
                            >
                                {{ $setupCommand }}
                            </code>
                            <button
                                type="button"
                                x-on:click="
                                    navigator.clipboard.writeText(@js($setupCommand))
                                    copied = true
                                    setTimeout(() => (copied = false), 1500)
                                "
                                class="absolute top-1.5 right-1.5 cursor-pointer rounded-md p-1 text-slate-400 hover:bg-white hover:text-slate-700 dark:hover:bg-slate-900"
                                aria-label="Copy command"
                                title="Copy"
                            >
                                <x-admin.icon name="copy" class="h-3.5 w-3.5" x-show="! copied" />
                                <x-admin.icon name="check" class="h-3.5 w-3.5 text-emerald-500" x-show="copied" x-cloak />
                            </button>
                        </div>
                    </div>
                @endif

                <dl class="mt-4 space-y-1.5 border-t border-slate-100 pt-3 text-xs dark:border-slate-800">
                    <div class="flex justify-between gap-3">
                        <dt class="text-slate-500 dark:text-slate-400">Stored backups</dt>
                        <dd class="tabular font-medium text-slate-800 dark:text-slate-100">{{ Number::fileSize($totalSize, 1) }}</dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-slate-500 dark:text-slate-400">Location</dt>
                        <dd class="truncate font-mono text-[11px] text-slate-600 dark:text-slate-300">
                            storage/app/private/{{ config('backup.directory') }}
                        </dd>
                    </div>
                </dl>
            </x-admin.card>
        </aside>
    </div>

    <x-admin.table
        :headers="$headers"
        :data="$backups"
        emptyMessage="No backups yet"
        emptyText="Use “Back up now” or switch on automatic backups."
        emptyIcon="save"
    >
        @foreach ($backups as $backup)
            <tr>
                <td>
                    <span class="block font-medium text-slate-900 dark:text-white">{{ local_datetime($backup->created_at) }}</span>
                    <span class="mt-0.5 flex items-center gap-1.5 text-xs text-slate-500 dark:text-slate-400">
                        <span
                            @class([
                                'rounded px-1.5 py-px text-[10px] font-semibold',
                                'bg-blue-50 text-blue-700 dark:bg-blue-500/10 dark:text-blue-300' => $backup->trigger === 'scheduled',
                                'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300' => $backup->trigger !== 'scheduled',
                            ])
                        >
                            {{ $backup->trigger === 'scheduled' ? 'Automatic' : 'Manual' }}
                        </span>
                        @if ($backup->user)
                            by {{ $backup->user->name }}
                        @endif

                        @if ($backup->finished_at && $backup->started_at)
                            · took {{ $backup->started_at->diffForHumans($backup->finished_at, true) }}
                        @endif
                    </span>
                </td>

                <td class="whitespace-nowrap">
                    <span class="flex gap-1">
                        @if ($backup->includes_database)
                            <span
                                class="rounded-md bg-slate-100 px-1.5 py-0.5 text-xs font-medium text-slate-600 dark:bg-slate-800 dark:text-slate-300"
                            >
                                Database
                            </span>
                        @endif

                        @if ($backup->includes_files)
                            <span
                                class="rounded-md bg-slate-100 px-1.5 py-0.5 text-xs font-medium text-slate-600 dark:bg-slate-800 dark:text-slate-300"
                            >
                                Files
                            </span>
                        @endif
                    </span>
                </td>

                <td class="tabular whitespace-nowrap text-slate-700 dark:text-slate-200">
                    {{ $backup->status === Backup::COMPLETED ? Number::fileSize($backup->size, 1) : '—' }}
                </td>

                <td class="min-w-40">
                    @if ($backup->status === Backup::COMPLETED)
                        <span class="inline-flex items-center gap-1.5 text-sm font-medium text-emerald-700 dark:text-emerald-400">
                            <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                            Completed
                        </span>
                    @elseif ($backup->status === Backup::FAILED)
                        <span
                            class="inline-flex items-center gap-1.5 text-sm font-medium text-red-600 dark:text-red-400"
                            title="{{ $backup->error }}"
                        >
                            <span class="h-1.5 w-1.5 rounded-full bg-red-500"></span>
                            Failed
                        </span>
                        <span class="block max-w-xs truncate text-xs text-slate-400" title="{{ $backup->error }}">{{ $backup->error }}</span>
                    @else
                        <span class="inline-flex items-center gap-1.5 text-sm font-medium text-blue-700 dark:text-blue-300">
                            <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-blue-500"></span>
                            {{ $backup->isStalled() ? 'Interrupted at ' . $backup->percent() . '%' : 'Running, ' . $backup->percent() . '%' }}
                        </span>
                        <span class="block max-w-xs truncate text-xs text-slate-400">{{ $backup->statusLine() }}</span>
                    @endif
                </td>

                @if ($canDownload || $canDelete)
                    <td class="whitespace-nowrap">
                        <div class="flex items-center justify-end gap-1.5">
                            @if ($canDownload && $backup->status === Backup::COMPLETED)
                                @if (count($backup->parts ?? []) === 1)
                                    <x-admin.button
                                        size="sm"
                                        variant="secondary"
                                        icon="download"
                                        :href="route('admin.backups.download', [$backup, $backup->parts[0]['file']])"
                                    >
                                        Download
                                    </x-admin.button>
                                @else
                                    <x-admin.dropdown width="w-72">
                                        <x-slot:trigger>
                                            <x-admin.button type="button" size="sm" variant="secondary" icon="download">
                                                Download ({{ count($backup->parts ?? []) }})
                                            </x-admin.button>
                                        </x-slot>

                                        @foreach ($backup->parts ?? [] as $part)
                                            <a
                                                href="{{ route('admin.backups.download', [$backup, $part['file']]) }}"
                                                role="menuitem"
                                                class="flex items-center gap-2.5 px-3 py-2 text-sm text-slate-700 hover:bg-slate-50 dark:text-slate-200 dark:hover:bg-slate-800"
                                            >
                                                <x-admin.icon
                                                    :name="str_ends_with($part['file'], '.zip') ? 'folder' : 'hash'"
                                                    class="h-4 w-4 text-slate-400"
                                                />
                                                <span class="min-w-0 flex-1 truncate">{{ $part['label'] }}</span>
                                                <span class="tabular shrink-0 text-xs text-slate-400">{{ Number::fileSize($part['size'], 1) }}</span>
                                            </a>
                                        @endforeach
                                    </x-admin.dropdown>
                                @endif
                            @endif

                            @if ($canDelete && (! $backup->isRunning() || $backup->isStalled()))
                                <x-admin.delete-button
                                    size="sm"
                                    :route="route('admin.backups.destroy', $backup)"
                                    :id="$backup->id"
                                    title="Delete this backup?"
                                    message="Its files are removed from the server. This cannot be undone."
                                />
                            @endif
                        </div>
                    </td>
                @endif
            </tr>
        @endforeach
    </x-admin.table>

    <p class="mt-3 flex items-start gap-2 text-xs text-slate-500 dark:text-slate-400">
        <x-admin.icon name="info" class="mt-px h-3.5 w-3.5 shrink-0" />
        To restore: import the database file with phpMyAdmin (Import tab) or
        <code class="font-mono">mysql</code>
        , and unzip the files into the project folder. Keep a copy somewhere other than this server too.
    </p>
</x-admin>
