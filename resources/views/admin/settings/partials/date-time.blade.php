@php
    use App\Support\LocalTime;
    use Illuminate\Support\Carbon;
    use Illuminate\Support\Js;

    $canEdit = auth()
        ->user()
        ->can('admin.settings.date-time.update');
    $current = ['zone' => LocalTime::zone(), 'date' => LocalTime::dateFormat(), 'time' => LocalTime::timeFormat()];
    $dateOptions = LocalTime::dateFormatOptions();
    $timeSamples = collect(LocalTime::TIME_FORMATS)
        ->keys()
        ->mapWithKeys(fn ($format) => [$format => Carbon::create(2026, 9, 24, 14, 30)->format($format)])
        ->all();
@endphp

<div class="space-y-5">
    @unless ($canEdit)
        @include('admin.settings.partials.read-only-notice')
    @endunless

    <form
        method="POST"
        action="{{ route('admin.settings.date-time.update') }}"
        x-data="{
            submitting: false,
            zone: {{ Js::from(old('timezone', $current['zone'])) }},
            date: {{ Js::from(old('date_format', $current['date'])) }},
            time: {{ Js::from(old('time_format', $current['time'])) }},
            dates: {{ Js::from($dateOptions) }},
            times: {{ Js::from($timeSamples) }},
            now: '',
            get example() {
                return this.dates[this.date] + ', ' + this.times[this.time]
            },
            tick() {
                try {
                    this.now = new Intl.DateTimeFormat('en-GB', {
                        timeZone: this.zone,
                        weekday: 'long',
                        hour: 'numeric',
                        minute: '2-digit',
                        hour12: this.time.startsWith('g'),
                    }).format(new Date())
                } catch {
                    this.now = ''
                }
            },
            init() {
                this.tick()
                setInterval(() => this.tick(), 15000)
                this.$watch('zone', () => this.tick())
                this.$watch('time', () => this.tick())
            },
        }"
        x-on:submit="submitting = true"
    >
        @csrf
        @method('PUT')

        <x-admin.card
            title="Date & time"
            text="One setting for the whole website: every date in the admin, emails and scheduled backups follow it."
            icon="clock"
        >
            @if ($canEdit)
                <x-slot:actions>
                    <x-admin.button size="sm" icon="save" x-bind:disabled="submitting">
                        <span x-text="submitting ? 'Saving…' : 'Save date & time'">Save date & time</span>
                    </x-admin.button>
                </x-slot>
            @endif

            <fieldset @disabled(! $canEdit) class="space-y-5">
                <x-admin.form.select
                    name="timezone"
                    label="Timezone"
                    :options="LocalTime::zoneOptions()"
                    :value="old('timezone', $current['zone'])"
                    :searchable="true"
                    :disabled="! $canEdit"
                    x-model="zone"
                    icon="globe"
                    :hint="'Search by city, e.g. Kolkata, Dubai or London. Automatic backups run at their set time in this timezone. The server itself runs on ' . config('app.timezone') . '.'"
                />

                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                    <x-admin.form.select
                        name="date_format"
                        label="Date format"
                        :options="$dateOptions"
                        :value="old('date_format', $current['date'])"
                        :disabled="! $canEdit"
                        x-model="date"
                    />
                    <x-admin.form.select
                        name="time_format"
                        label="Time format"
                        :options="LocalTime::timeFormatOptions()"
                        :value="old('time_format', $current['time'])"
                        :disabled="! $canEdit"
                        x-model="time"
                    />
                </div>

                <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl bg-slate-50 px-4 py-3 dark:bg-slate-800/60">
                    <div>
                        <p class="text-xs text-slate-500 dark:text-slate-400">Dates will look like</p>
                        <p class="font-medium text-slate-900 dark:text-white" x-text="example">{{ LocalTime::dateTime(now()) }}</p>
                    </div>
                    <div class="text-right" x-show="now">
                        <p class="text-xs text-slate-500 dark:text-slate-400">Time there now</p>
                        <p class="tabular font-medium text-slate-900 dark:text-white" x-text="now"></p>
                    </div>
                </div>
            </fieldset>
        </x-admin.card>
    </form>
</div>
