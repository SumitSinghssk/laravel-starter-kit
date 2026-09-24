@php
    $maintenance = \App\Support\Maintenance::settings();
    $canUpdate = auth()
        ->user()
        ->can('admin.settings.maintenance.update');
    $myIp = request()->ip();
@endphp

<form
    method="POST"
    action="{{ route('admin.settings.maintenance.update') }}"
    x-data="{
        submitting: false,
        enabled: @js((bool) old('enabled', $maintenance['enabled'])),
        ips: @js(old('allowed_ips', implode("\n", $maintenance['allowed_ips']))),
        addMyIp() {
            const lines = this.ips.split(/\s+/).filter(Boolean)
            if (! lines.includes(@js($myIp)))
                this.ips = [...lines, @js($myIp)].join('\n')
        },
    }"
    x-on:submit="submitting = true"
    class="space-y-6"
>
    @csrf

    @unless ($canUpdate)
        @include('admin.settings.partials.read-only-notice')
    @endunless

    <div
        class="flex flex-wrap items-center justify-between gap-4 rounded-xl border p-4 transition-colors"
        x-bind:class="
            enabled
                ? 'border-amber-300 bg-amber-50 dark:border-amber-500/30 dark:bg-amber-500/10'
                : 'border-slate-200/80 bg-white dark:border-slate-800 dark:bg-slate-900'
        "
    >
        <div class="flex items-center gap-3">
            <span
                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl"
                x-bind:class="
                    enabled
                        ? 'bg-amber-400 text-amber-950'
                        : 'bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-400'
                "
            >
                <x-admin.icon name="lock" class="h-5 w-5" x-show="enabled" />
                <x-admin.icon name="globe" class="h-5 w-5" x-show="! enabled" />
            </span>
            <div>
                <p
                    class="text-sm font-semibold text-slate-900 dark:text-white"
                    x-text="enabled ? 'Maintenance mode is on' : 'The website is live'"
                ></p>
                <p
                    class="text-xs text-slate-500 dark:text-slate-400"
                    x-text="
                        enabled
                            ? 'Visitors see the maintenance page. You and other signed-in admins still see the site.'
                            : 'Everyone can use the website normally.'
                    "
                ></p>
            </div>
        </div>

        <x-admin.form.toggle
            name="enabled"
            label="Maintenance mode"
            label-position="left"
            :checked="(bool) $maintenance['enabled']"
            x-model="enabled"
            :disabled="! $canUpdate"
        />
    </div>

    <fieldset @disabled(! $canUpdate) class="space-y-6">
        <x-admin.card title="Maintenance page" text="What visitors see while the site is down." icon="file-text">
            <x-slot:actions>
                <x-admin.button
                    size="sm"
                    variant="secondary"
                    icon="external-link"
                    :href="route('admin.settings.maintenance.preview')"
                    target="_blank"
                    rel="noopener"
                >
                    Preview
                </x-admin.button>
            </x-slot>

            <div class="space-y-5">
                <x-admin.form.input name="title" label="Heading" :value="$maintenance['title']" maxlength="120" required />

                <x-admin.form.textarea name="message" label="Message" rows="3" :value="$maintenance['message']" maxlength="1000" required />

                <x-admin.form.date-picker
                    name="ends_at"
                    label="Expected back"
                    with-time
                    :value="$maintenance['ends_at'] ? \Illuminate\Support\Carbon::parse($maintenance['ends_at'])->format('Y-m-d\TH:i') : ''"
                    hint="Optional. Shown on the page and told to search engines (Retry-After); it does not switch maintenance off by itself."
                />
            </div>
        </x-admin.card>

        <x-admin.card title="Who can still see the site" icon="shield-check">
            <div class="space-y-4">
                <p class="flex items-start gap-2 text-sm text-slate-600 dark:text-slate-300">
                    <x-admin.icon name="check-circle" class="mt-0.5 h-4 w-4 shrink-0 text-emerald-500" />
                    Anyone signed in to this admin panel, in the same browser.
                </p>

                <div>
                    <div class="mb-1.5 flex items-center justify-between gap-3">
                        <label for="allowed_ips" class="text-sm font-medium text-slate-700 dark:text-slate-200">Also allow these IP addresses</label>
                        <button
                            type="button"
                            x-on:click="addMyIp()"
                            class="cursor-pointer text-xs font-medium text-blue-600 hover:underline dark:text-blue-400"
                        >
                            Add my IP ({{ $myIp }})
                        </button>
                    </div>
                    <textarea
                        id="allowed_ips"
                        name="allowed_ips"
                        rows="3"
                        x-model="ips"
                        placeholder="203.0.113.7&#10;198.51.100.0/24"
                        spellcheck="false"
                        class="{{ \App\Support\FormField::controlClasses($errors->has('allowed_ips')) }} px-3 py-2 font-mono text-xs"
                    ></textarea>
                    <x-admin.form.error for="allowed_ips" />
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                        One per line. Handy for testing on a phone or letting a client check the site without an admin login.
                    </p>
                </div>
            </div>
        </x-admin.card>
    </fieldset>

    @if ($canUpdate)
        @include('admin.settings.partials.save-bar', ['label' => 'Save maintenance settings', 'note' => 'Takes effect on the website as soon as you save.'])
    @endif
</form>
