@php
    use App\Support\MailSettings;
    use Illuminate\Support\Js;

    $mail = MailSettings::forForm();
    $canEdit = auth()->user()->can('admin.settings.email.update');
    $value = fn (string $key) => old($key, $mail[$key]);
    $field = 'h-10 w-full rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-900 shadow-xs outline-none transition focus:border-blue-400 focus:ring-3 focus:ring-blue-500/20 disabled:bg-slate-50 disabled:text-slate-500 dark:border-slate-700 dark:bg-slate-900 dark:text-white dark:disabled:bg-slate-800/60';
    $label = 'mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-200';
    $presets = collect(MailSettings::PRESETS)->map(fn ($p) => ['host' => $p['host'], 'port' => $p['port'], 'encryption' => $p['encryption'], 'note' => $p['note']])->all();
@endphp

<div class="space-y-5">
    @unless ($canEdit)
        @include('admin.settings.partials.read-only-notice')
    @endunless

    <form
        method="POST"
        action="{{ route('admin.settings.email.update') }}"
        x-data="{
            submitting: false,
            enabled: {{ Js::from((bool) $value('enabled')) }},
            provider: {{ Js::from($value('provider')) }},
            host: {{ Js::from($value('host')) }},
            port: {{ Js::from((string) $value('port')) }},
            encryption: {{ Js::from($value('encryption')) }},
            showPassword: false,
            removePassword: false,
            presets: {{ Js::from($presets) }},
            testEmail: {{ Js::from(old('test_email', auth()->user()->email)) }},
            testing: false,
            result: null,
            applyPreset() {
                const preset = this.presets[this.provider];
                if (preset && this.provider !== 'custom') {
                    this.host = preset.host;
                    this.port = String(preset.port);
                    this.encryption = preset.encryption;
                }
            },
            setEncryption(value) {
                this.encryption = value;
                if (value === 'ssl' && this.port === '587') this.port = '465';
                if (value === 'tls' && this.port === '465') this.port = '587';
            },
            async test() {
                this.testing = true;
                this.result = null;
                const form = new FormData(this.$el.closest('form'));
                form.set('test_email', this.testEmail);
                form.set('enabled', '1');
                form.delete('_method');
                try {
                    const { data } = await window.axios.post({{ Js::from(route('admin.settings.email.test')) }}, form);
                    this.result = data;
                } catch (error) {
                    const response = error.response;
                    if (response?.status === 429) {
                        this.result = { ok: false, message: 'Too many tests in a row. Wait a minute and try again.' };
                    } else if (response?.data?.errors) {
                        this.result = { ok: false, message: Object.values(response.data.errors)[0][0] };
                    } else if (response?.data?.message) {
                        this.result = response.data;
                    } else {
                        this.result = { ok: false, message: 'The test could not run. Check your connection and try again.' };
                    }
                } finally {
                    this.testing = false;
                }
            },
        }"
        x-on:submit="submitting = true"
        class="space-y-5"
    >
        @csrf

        <x-admin.card title="Email (SMTP)" text="How the website sends email: contact form alerts, password resets and notifications. Works with any provider." icon="mail">
            @if ($canEdit)
                <x-slot:actions>
                    <x-admin.button type="submit" size="sm" icon="save">
                        <span x-text="submitting ? 'Saving…' : 'Save email settings'">Save email settings</span>
                    </x-admin.button>
                </x-slot>
            @endif

            <fieldset @disabled(! $canEdit) class="space-y-6">
                <label
                    class="flex cursor-pointer items-start justify-between gap-4 rounded-xl border p-4 transition"
                    x-bind:class="enabled ? 'border-emerald-200 bg-emerald-50/60 dark:border-emerald-500/30 dark:bg-emerald-500/10' : 'border-slate-200 bg-slate-50/60 dark:border-slate-700 dark:bg-slate-800/40'"
                >
                    <span class="flex items-start gap-3">
                        <span class="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-lg" x-bind:class="enabled ? 'bg-emerald-100 text-emerald-600 dark:bg-emerald-500/20 dark:text-emerald-300' : 'bg-slate-200/70 text-slate-500 dark:bg-slate-700 dark:text-slate-300'">
                            <x-admin.icon name="send" class="h-4.5 w-4.5" />
                        </span>
                        <span>
                            <span class="block text-sm font-semibold text-slate-900 dark:text-white">Send the website's email through this SMTP server</span>
                            <span class="mt-0.5 block text-xs text-slate-500 dark:text-slate-400" x-show="enabled">On. Every email the site sends uses the settings below.</span>
                            <span class="mt-0.5 block text-xs text-slate-500 dark:text-slate-400" x-show="! enabled">Off. The site uses the server's own mail setup (<span class="font-mono">{{ MailSettings::fallbackMailer() }}</span>), which usually doesn't deliver real email.</span>
                        </span>
                    </span>
                    <input type="hidden" name="enabled" value="0" />
                    <input type="checkbox" name="enabled" value="1" x-model="enabled" class="peer sr-only" />
                    <span class="relative mt-1 h-6 w-11 shrink-0 rounded-full bg-slate-300 transition peer-checked:bg-emerald-500 peer-focus-visible:ring-3 peer-focus-visible:ring-blue-500/30 dark:bg-slate-600" aria-hidden="true">
                        <span class="absolute top-0.5 left-0.5 h-5 w-5 rounded-full bg-white shadow transition" x-bind:class="enabled && 'translate-x-5'"></span>
                    </span>
                </label>

                <div>
                    <x-admin.form.select
                        id="mail-provider"
                        name="provider"
                        label="Email provider"
                        :options="array_map(fn ($preset) => $preset['label'], MailSettings::PRESETS)"
                        :value="$value('provider')"
                        :searchable="true"
                        empty-message="No provider matches. Choose Other / custom."
                        x-model="provider"
                        x-on:change="applyPreset()"
                    />
                    <p class="mt-2 flex items-start gap-2 rounded-lg bg-blue-50/70 px-3 py-2 text-xs text-blue-800 dark:bg-blue-500/10 dark:text-blue-200">
                        <x-admin.icon name="info" class="mt-px h-3.5 w-3.5 shrink-0" />
                        <span x-text="presets[provider]?.note">{{ MailSettings::PRESETS[$value('provider')]['note'] ?? '' }}</span>
                    </p>
                </div>

                <div>
                    <p class="mb-3 text-xs font-semibold tracking-wide text-slate-400 uppercase">Server</p>
                    <div class="grid gap-4 sm:grid-cols-[minmax(0,1fr)_8rem]">
                        <div>
                            <label for="mail-host" class="{{ $label }}">SMTP host</label>
                            <input id="mail-host" name="host" type="text" value="{{ $value('host') }}" x-model="host" placeholder="smtp.example.com" autocomplete="off" spellcheck="false" class="{{ $field }} font-mono" />
                            <x-admin.form.error for="host" />
                        </div>
                        <div>
                            <label for="mail-port" class="{{ $label }}">Port</label>
                            <input id="mail-port" name="port" type="number" min="1" max="65535" value="{{ $value('port') }}" x-model="port" class="{{ $field }} tabular" />
                            <x-admin.form.error for="port" />
                        </div>
                    </div>

                    <div class="mt-4">
                        <p class="{{ $label }}">Encryption</p>
                        <input type="hidden" name="encryption" x-bind:value="encryption" value="{{ $value('encryption') }}" />
                        <div class="inline-flex rounded-lg bg-slate-100 p-1 dark:bg-slate-800">
                            @foreach (MailSettings::ENCRYPTIONS as $key => $name)
                                <button
                                    type="button"
                                    x-on:click="setEncryption({{ Js::from($key) }})"
                                    @if ($value('encryption') === $key) aria-pressed="true" @endif
                                    x-bind:aria-pressed="(encryption === {{ Js::from($key) }}).toString()"
                                    class="cursor-pointer rounded-md px-3 py-1.5 text-sm font-medium text-slate-500 transition hover:text-slate-900 aria-pressed:bg-white aria-pressed:text-slate-900 aria-pressed:shadow-xs dark:text-slate-400 dark:hover:text-white dark:aria-pressed:bg-slate-900 dark:aria-pressed:text-white"
                                >{{ $name }}</button>
                            @endforeach
                        </div>
                        <p class="mt-1.5 text-xs text-slate-500 dark:text-slate-400">Usually TLS with port 587, or SSL with port 465.</p>
                    </div>
                </div>

                <div>
                    <p class="mb-3 text-xs font-semibold tracking-wide text-slate-400 uppercase">Login</p>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label for="mail-username" class="{{ $label }}">Username</label>
                            <input id="mail-username" name="username" type="text" value="{{ $value('username') }}" autocomplete="off" spellcheck="false" placeholder="Often your full email address" class="{{ $field }}" />
                            <x-admin.form.error for="username" />
                        </div>
                        <div>
                            <label for="mail-password" class="{{ $label }}">Password</label>
                            <div class="relative">
                                <input
                                    id="mail-password"
                                    name="password"
                                    x-bind:type="showPassword ? 'text' : 'password'"
                                    type="password"
                                    autocomplete="new-password"
                                    x-bind:disabled="removePassword"
                                    placeholder="{{ $mail['has_password'] ? '•••••••• saved · type to change' : 'Password or app password' }}"
                                    class="{{ $field }} pr-10"
                                />
                                <button type="button" x-on:click="showPassword = ! showPassword" class="absolute inset-y-0 right-0 flex w-10 cursor-pointer items-center justify-center text-slate-400 hover:text-slate-700 dark:hover:text-slate-200" x-bind:aria-label="showPassword ? 'Hide password' : 'Show password'">
                                    <x-admin.icon name="eye" class="h-4 w-4" x-show="! showPassword" />
                                    <x-admin.icon name="eye-off" class="h-4 w-4" x-show="showPassword" x-cloak />
                                </button>
                            </div>
                            <x-admin.form.error for="password" />
                            @if ($mail['has_password'])
                                <p class="mt-1.5 flex items-center justify-between gap-3 text-xs text-slate-500 dark:text-slate-400">
                                    <span class="flex items-center gap-1.5"><x-admin.icon name="lock" class="h-3.5 w-3.5" /> Saved and encrypted. Leave empty to keep it.</span>
                                    <label class="flex cursor-pointer items-center gap-1.5 text-red-600 dark:text-red-400">
                                        <input type="checkbox" name="remove_password" value="1" x-model="removePassword" class="h-3.5 w-3.5 rounded border-slate-300 text-red-600" />
                                        Remove
                                    </label>
                                </p>
                            @else
                                <p class="mt-1.5 text-xs text-slate-500 dark:text-slate-400">Stored encrypted and never shown again.</p>
                            @endif
                        </div>
                    </div>
                </div>

                <div>
                    <p class="mb-3 text-xs font-semibold tracking-wide text-slate-400 uppercase">Sender</p>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label for="mail-from" class="{{ $label }}">From address</label>
                            <input id="mail-from" name="from_address" type="email" value="{{ $value('from_address') }}" placeholder="hello@yourdomain.com" class="{{ $field }}" />
                            <x-admin.form.error for="from_address" />
                            <p class="mt-1.5 text-xs text-slate-500 dark:text-slate-400">Must be an address the account is allowed to send from.</p>
                        </div>
                        <div>
                            <label for="mail-from-name" class="{{ $label }}">From name</label>
                            <input id="mail-from-name" name="from_name" type="text" value="{{ $value('from_name') }}" placeholder="{{ \App\Helpers\Settings::appName() }}" maxlength="120" class="{{ $field }}" />
                            <x-admin.form.error for="from_name" />
                        </div>
                        <div class="sm:col-span-2">
                            <label for="mail-reply" class="{{ $label }}">Reply-to <span class="font-normal text-slate-400">· optional</span></label>
                            <input id="mail-reply" name="reply_to" type="email" value="{{ $value('reply_to') }}" placeholder="Where replies should go, if different" class="{{ $field }}" />
                            <x-admin.form.error for="reply_to" />
                        </div>
                    </div>
                </div>
            </fieldset>
        </x-admin.card>

        @if ($canEdit)
            <x-admin.card title="Send a test email" text="Uses the settings above, even before you save them." icon="send">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-start">
                    <div class="min-w-0 flex-1">
                        <label for="mail-test" class="sr-only">Send the test to</label>
                        <input id="mail-test" type="email" x-model="testEmail" x-on:keydown.enter.prevent="test()" placeholder="you@example.com" class="{{ $field }}" />
                    </div>
                    <x-admin.button type="button" icon="send" x-on:click="test()" x-bind:disabled="testing || ! testEmail">
                        <span x-text="testing ? 'Sending…' : 'Send test email'">Send test email</span>
                    </x-admin.button>
                </div>

                <p class="mt-3 flex items-center gap-2 text-sm text-slate-500 dark:text-slate-400" x-show="testing" x-cloak>
                    <x-admin.icon name="refresh" class="h-4 w-4 animate-spin" />
                    Connecting to <span class="font-mono" x-text="host + ':' + port"></span>… this can take up to 15 seconds.
                </p>

                <template x-if="result">
                    <div
                        class="mt-4 rounded-xl border px-4 py-3 text-sm"
                        x-bind:class="result.ok ? 'border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-200' : 'border-red-200 bg-red-50 text-red-800 dark:border-red-500/30 dark:bg-red-500/10 dark:text-red-200'"
                        role="status"
                    >
                        <p class="flex items-start gap-2 font-medium">
                            <x-admin.icon name="check-circle" class="mt-0.5 h-4 w-4 shrink-0" x-show="result.ok" />
                            <x-admin.icon name="alert-circle" class="mt-0.5 h-4 w-4 shrink-0" x-show="! result.ok" />
                            <span x-text="result.message"></span>
                        </p>
                        <template x-if="result.detail">
                            <details class="mt-2 pl-6 text-xs">
                                <summary class="cursor-pointer font-medium opacity-80">What the mail server said</summary>
                                <p class="mt-1.5 rounded-lg bg-white/60 p-2 font-mono break-words dark:bg-slate-900/40" x-text="result.detail"></p>
                            </details>
                        </template>
                        <p class="mt-1 pl-6 text-xs opacity-75" x-show="result.ok && ! enabled">It works. Turn on the switch above and save to use it for the website's email.</p>
                    </div>
                </template>
            </x-admin.card>
        @endif
    </form>
</div>
