@php
    use App\Models\BlockedRequest;
    use App\Models\IpBlock;
    use App\Support\SecuritySettings;
    use Illuminate\Support\Js;

    $canEdit = auth()
        ->user()
        ->can('admin.settings.security.update');
    $security = SecuritySettings::forForm();
    $value = fn (string $key) => old($key, $security[$key]);
    $blocks = IpBlock::with('creator:id,name')
        ->active()
        ->latest()
        ->get();
    $recent = BlockedRequest::latest('created_at')
        ->limit(40)
        ->get();
    $today = BlockedRequest::where('created_at', '>=', now()->subDay())
        ->selectRaw('reason, count(*) as total')
        ->groupBy('reason')
        ->pluck('total', 'reason');
    $number = 'h-10 w-full rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-900 shadow-xs outline-none focus:border-blue-400 focus:ring-3 focus:ring-blue-500/20 disabled:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-white dark:disabled:bg-slate-800/60';
    $label = 'mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-200';
    $help = 'mt-1.5 text-xs text-slate-500 dark:text-slate-400';
    $durations = ['60' => '1 hour', '1440' => '1 day', '10080' => '1 week', 'forever' => 'Until I unblock it'];
@endphp

<div class="space-y-6">
    @unless ($canEdit)
        @include('admin.settings.partials.read-only-notice')
    @endunless

    <form
        method="POST"
        action="{{ route('admin.settings.security.update') }}"
        class="space-y-6"
        x-data="{
            submitting: false,
            lockout: {{ Js::from((bool) $value('lockout_enabled')) }},
            lockMinutes: {{ Js::from((int) $value('lockout_minutes')) }},
            idle: {{ Js::from((int) $value('idle_minutes')) }},
            honeypot: {{ Js::from((bool) $value('honeypot')) }},
            captcha: {{ Js::from($value('captcha')) }},
            autoBlock: {{ Js::from((bool) $value('auto_block')) }},
        }"
        x-on:submit="submitting = true"
    >
        @csrf
        @method('PUT')

        <fieldset @disabled(! $canEdit) class="space-y-6">
            <x-admin.card
                id="settings-lockout"
                class="scroll-mt-24"
                title="Account lockout"
                text="Stops password guessing by locking an account after repeated wrong passwords or 2FA codes."
                icon="lock"
            >
                <div class="space-y-5">
                    <x-admin.form.toggle
                        name="lockout_enabled"
                        label="Lock accounts after too many failed sign-ins"
                        hint="The owner gets an email and admins get a notification when an account locks."
                        :checked="(bool) $value('lockout_enabled')"
                        :disabled="! $canEdit"
                        x-model="lockout"
                    />

                    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2" x-bind:class="! lockout && 'opacity-50'">
                        <div>
                            <label for="lockout_attempts" class="{{ $label }}">Lock after</label>
                            <div class="flex items-center gap-2">
                                <input
                                    id="lockout_attempts"
                                    name="lockout_attempts"
                                    type="number"
                                    min="3"
                                    max="50"
                                    value="{{ $value('lockout_attempts') }}"
                                    class="{{ $number }}"
                                />
                                <span class="shrink-0 text-sm text-slate-500">failed tries</span>
                            </div>
                            @error('lockout_attempts')
                                <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label for="lockout_minutes" class="{{ $label }}">Keep locked for</label>
                            <div class="flex items-center gap-2">
                                <input
                                    id="lockout_minutes"
                                    name="lockout_minutes"
                                    type="number"
                                    min="0"
                                    max="10080"
                                    x-model.number="lockMinutes"
                                    value="{{ $value('lockout_minutes') }}"
                                    class="{{ $number }}"
                                />
                                <span class="shrink-0 text-sm text-slate-500">minutes</span>
                            </div>
                            <p
                                class="{{ $help }}"
                                x-text="
                                    lockMinutes > 0
                                        ? 'Unlocks by itself after ' +
                                          lockMinutes +
                                          ' minutes. Admins can unlock sooner.'
                                        : 'Stays locked until an admin unlocks it (0).'
                                "
                            ></p>
                        </div>
                    </div>
                </div>
            </x-admin.card>

            <x-admin.card
                id="settings-idle"
                class="scroll-mt-24"
                title="Idle sign-out"
                text="Signs people out of the admin when they walk away without signing out."
                icon="clock"
            >
                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                    <div>
                        <label for="idle_minutes" class="{{ $label }}">Sign out after</label>
                        <div class="flex items-center gap-2">
                            <input
                                id="idle_minutes"
                                name="idle_minutes"
                                type="number"
                                min="0"
                                max="1440"
                                x-model.number="idle"
                                value="{{ $value('idle_minutes') }}"
                                class="{{ $number }}"
                            />
                            <span class="shrink-0 text-sm text-slate-500">minutes idle</span>
                        </div>
                        <p
                            class="{{ $help }}"
                            x-text="
                                idle > 0
                                    ? 'A “Still there?” warning shows 60 seconds before. Typing or moving the mouse in any admin tab counts as activity.'
                                    : 'Off (0): people stay signed in until they sign out.'
                            "
                        ></p>
                    </div>
                </div>
            </x-admin.card>

            <x-admin.card
                id="settings-limits"
                class="scroll-mt-24"
                title="Rate limits"
                text="The most requests one visitor can make per minute before getting “Too many requests”."
                icon="zap"
            >
                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                    @foreach (['login_per_minute' => ['Admin sign-in', 'Per email address and IP.'], 'password_reset_per_minute' => ['Forgot / reset password', 'Per IP.'], 'two_factor_per_minute' => ['Two-factor codes', 'Per IP.'], 'forms_per_minute' => ['Website forms', 'Per IP, for contact and other forms.']] as $key => [$title, $note])
                        <div>
                            <label for="{{ $key }}" class="{{ $label }}">{{ $title }}</label>
                            <div class="flex items-center gap-2">
                                <input
                                    id="{{ $key }}"
                                    name="{{ $key }}"
                                    type="number"
                                    min="1"
                                    max="120"
                                    value="{{ $value($key) }}"
                                    class="{{ $number }}"
                                />
                                <span class="shrink-0 text-sm text-slate-500">per minute</span>
                            </div>
                            <p class="{{ $help }}">{{ $note }}</p>
                        </div>
                    @endforeach
                </div>
            </x-admin.card>

            <x-admin.card
                id="settings-bots"
                class="scroll-mt-24"
                title="Bot protection"
                text="Keeps spam bots out of the sign-in and website forms."
                icon="bot"
            >
                <div class="space-y-5">
                    <x-admin.form.toggle
                        name="honeypot"
                        label="Invisible bot trap"
                        hint="Adds a hidden field only bots fill in, and rejects forms sent faster than a person could. Visitors never see it."
                        :checked="(bool) $value('honeypot')"
                        :disabled="! $canEdit"
                        x-model="honeypot"
                    />

                    <div x-show="honeypot" class="max-w-xs">
                        <label for="min_submit_seconds" class="{{ $label }}">Reject forms sent within</label>
                        <div class="flex items-center gap-2">
                            <input
                                id="min_submit_seconds"
                                name="min_submit_seconds"
                                type="number"
                                min="0"
                                max="30"
                                value="{{ $value('min_submit_seconds') }}"
                                class="{{ $number }}"
                            />
                            <span class="shrink-0 text-sm text-slate-500">seconds</span>
                        </div>
                    </div>

                    <div class="border-t border-slate-100 pt-5 dark:border-slate-800">
                        <x-admin.form.select
                            name="captcha"
                            label="“I am human” check (CAPTCHA)"
                            :options="SecuritySettings::CAPTCHAS"
                            :value="$value('captcha')"
                            :disabled="! $canEdit"
                            x-model="captcha"
                            hint="Both are free. Turnstile is usually invisible to visitors; hCaptcha sometimes shows a picture puzzle."
                        />

                        <div x-show="captcha !== 'none'" x-cloak class="mt-5 space-y-5">
                            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                                <x-admin.form.input
                                    name="captcha_site_key"
                                    label="Site key"
                                    :value="$value('captcha_site_key')"
                                    :disabled="! $canEdit"
                                    autocomplete="off"
                                />
                                <div>
                                    <x-admin.form.input
                                        type="password"
                                        name="captcha_secret"
                                        label="Secret key"
                                        :placeholder="$security['has_captcha_secret'] ? 'saved · type to change' : 'From your CAPTCHA provider'"
                                        :disabled="! $canEdit"
                                        autocomplete="new-password"
                                    />
                                    @if ($security['has_captcha_secret'])
                                        <label class="mt-1.5 flex items-center gap-2 text-xs text-slate-500">
                                            <input type="checkbox" name="remove_captcha_secret" value="1" class="rounded border-slate-300" />
                                            Remove the saved secret
                                        </label>
                                    @endif
                                </div>
                            </div>
                            <x-admin.form.toggle
                                name="captcha_on_login"
                                label="Also ask on the admin sign-in and forgot-password pages"
                                hint="Website forms always use it when it's on."
                                :checked="(bool) $value('captcha_on_login')"
                                :disabled="! $canEdit"
                            />
                            <p class="text-xs text-slate-500 dark:text-slate-400">
                                Create free keys, with this site's domain added, at
                                <a
                                    href="https://dash.cloudflare.com/?to=/:account/turnstile"
                                    target="_blank"
                                    rel="noopener"
                                    class="text-blue-600 hover:underline dark:text-blue-400"
                                >
                                    Cloudflare Turnstile
                                </a>
                                or
                                <a
                                    href="https://dashboard.hcaptcha.com/sites"
                                    target="_blank"
                                    rel="noopener"
                                    class="text-blue-600 hover:underline dark:text-blue-400"
                                >
                                    hCaptcha
                                </a>
                            </p>
                        </div>
                    </div>
                </div>
            </x-admin.card>

            <x-admin.card
                id="settings-autoblock"
                class="scroll-mt-24"
                title="Automatic IP blocking"
                text="Blocks an IP address for a while when it keeps hitting the limits above."
                icon="shield"
            >
                <div class="space-y-5">
                    <x-admin.form.toggle
                        name="auto_block"
                        label="Block abusive IP addresses automatically"
                        hint="Admins get a notification each time. You can unblock from the list below."
                        :checked="(bool) $value('auto_block')"
                        :disabled="! $canEdit"
                        x-model="autoBlock"
                    />

                    <div class="grid grid-cols-1 gap-5 sm:grid-cols-3" x-bind:class="! autoBlock && 'opacity-50'">
                        <div>
                            <label for="auto_block_after" class="{{ $label }}">After</label>
                            <div class="flex items-center gap-2">
                                <input
                                    id="auto_block_after"
                                    name="auto_block_after"
                                    type="number"
                                    min="5"
                                    max="1000"
                                    value="{{ $value('auto_block_after') }}"
                                    class="{{ $number }}"
                                />
                                <span class="shrink-0 text-sm text-slate-500">blocks</span>
                            </div>
                        </div>
                        <div>
                            <label for="auto_block_window" class="{{ $label }}">Within</label>
                            <div class="flex items-center gap-2">
                                <input
                                    id="auto_block_window"
                                    name="auto_block_window"
                                    type="number"
                                    min="1"
                                    max="1440"
                                    value="{{ $value('auto_block_window') }}"
                                    class="{{ $number }}"
                                />
                                <span class="shrink-0 text-sm text-slate-500">minutes</span>
                            </div>
                        </div>
                        <div>
                            <label for="auto_block_minutes" class="{{ $label }}">Block for</label>
                            <div class="flex items-center gap-2">
                                <input
                                    id="auto_block_minutes"
                                    name="auto_block_minutes"
                                    type="number"
                                    min="1"
                                    max="43200"
                                    value="{{ $value('auto_block_minutes') }}"
                                    class="{{ $number }}"
                                />
                                <span class="shrink-0 text-sm text-slate-500">minutes</span>
                            </div>
                        </div>
                    </div>

                    <div class="max-w-xs border-t border-slate-100 pt-5 dark:border-slate-800">
                        <label for="log_days" class="{{ $label }}">Keep the blocked-requests log for</label>
                        <div class="flex items-center gap-2">
                            <input
                                id="log_days"
                                name="log_days"
                                type="number"
                                min="1"
                                max="365"
                                value="{{ $value('log_days') }}"
                                class="{{ $number }}"
                            />
                            <span class="shrink-0 text-sm text-slate-500">days</span>
                        </div>
                    </div>
                </div>
            </x-admin.card>
        </fieldset>

        @if ($canEdit)
            @include('admin.settings.partials.save-bar', ['label' => 'Save security settings', 'note' => 'Saves lockout, idle sign-out, rate limits, bot protection and automatic blocking.'])
        @endif
    </form>

    <x-admin.card
        id="settings-blocked-ips"
        class="scroll-mt-24"
        title="Blocked IP addresses"
        :subtitle="(string) $blocks->count()"
        text="Anyone from these addresses gets an “Access blocked” page on the whole site."
        icon="shield"
        :padded="false"
    >
        @if ($canEdit)
            <form
                method="POST"
                action="{{ route('admin.settings.security.blocks.store') }}"
                class="grid grid-cols-1 items-start gap-3 border-b border-slate-100 p-4 sm:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_10rem_auto] sm:p-5 dark:border-slate-800"
            >
                @csrf
                <x-admin.form.input name="ip" label="IP address or range" placeholder="203.0.113.7 or 203.0.113.0/24" autocomplete="off" />
                <x-admin.form.input name="reason" label="Reason" placeholder="Optional" />
                <x-admin.form.select name="duration" label="Block for" :options="$durations" :value="old('duration', 'forever')" />
                <div class="sm:pt-6.5">
                    <x-admin.button icon="plus" full>Block</x-admin.button>
                </div>
                <p class="text-xs text-slate-500 sm:col-span-4 dark:text-slate-400">
                    Your own IP right now is {{ request()->ip() }}; you can't block yourself.
                </p>
            </form>
        @endif

        @forelse ($blocks as $block)
            <div
                class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-4 py-3 last:border-b-0 sm:px-5 dark:border-slate-800"
            >
                <div class="min-w-0">
                    <p class="flex flex-wrap items-center gap-2 text-sm">
                        <span class="font-mono font-medium text-slate-900 dark:text-white">{{ $block->ip }}</span>
                        @if ($block->automatic)
                            <x-admin.status-badge tone="warning" label="Automatic" />
                        @endif
                    </p>
                    <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">
                        {{ $block->reason ?: 'No reason given' }}
                        · {{ $block->expires_at ? 'until ' . local_datetime($block->expires_at) : 'until unblocked' }} · added
                        {{ $block->created_at->diffForHumans() }}{{ $block->creator ? ' by ' . $block->creator->name : '' }}
                        @if ($block->hits)
                            · {{ $block->hits }} {{ str('visit')->plural($block->hits) }} stopped
                        @endif
                    </p>
                </div>
                @if ($canEdit)
                    <form method="POST" action="{{ route('admin.settings.security.blocks.destroy', $block) }}">
                        @csrf
                        @method('DELETE')
                        <x-admin.button size="sm" variant="secondary" icon="x">Unblock</x-admin.button>
                    </form>
                @endif
            </div>
        @empty
            <p class="px-4 py-8 text-center text-sm text-slate-400 sm:px-5">No IP addresses are blocked.</p>
        @endforelse
    </x-admin.card>

    <x-admin.card
        id="settings-blocked-log"
        class="scroll-mt-24"
        title="Blocked requests"
        text="What was stopped recently, and why."
        icon="list"
        :padded="false"
    >
        @if ($canEdit && $recent->isNotEmpty())
            <x-slot:actions>
                <x-admin.confirm-button
                    :action="route('admin.settings.security.log.clear')"
                    method="DELETE"
                    title="Clear the blocked-requests log?"
                    message="This only clears the history. Blocked IPs stay blocked."
                    confirm="Clear log"
                    icon="trash"
                    size="sm"
                    variant="secondary"
                >
                    Clear log
                </x-admin.confirm-button>
            </x-slot>
        @endif

        <dl
            class="grid grid-cols-2 gap-px border-b border-slate-100 bg-slate-100 sm:grid-cols-3 lg:grid-cols-6 dark:border-slate-800 dark:bg-slate-800"
        >
            @foreach (BlockedRequest::REASONS as $reason => $reasonLabel)
                <div class="bg-white px-4 py-3 dark:bg-slate-900">
                    <dt class="text-[11px] text-slate-500 dark:text-slate-400">{{ $reasonLabel }}</dt>
                    <dd class="tabular text-lg font-semibold text-slate-900 dark:text-white">{{ $today[$reason] ?? 0 }}</dd>
                </div>
            @endforeach
        </dl>
        <p class="border-b border-slate-100 px-4 py-2 text-xs text-slate-400 sm:px-5 dark:border-slate-800">Counts for the last 24 hours.</p>

        <ul class="divide-y divide-slate-100 dark:divide-slate-800">
            @forelse ($recent as $entry)
                <li class="flex flex-wrap items-center justify-between gap-x-4 gap-y-1 px-4 py-2.5 text-sm sm:px-5">
                    <span class="flex min-w-0 items-center gap-2">
                        <span class="font-mono text-slate-900 dark:text-white">{{ $entry->ip }}</span>
                        <x-admin.status-badge
                            :tone="$entry->reason === 'ip_blocked' ? 'danger' : 'warning'"
                            :label="$entry->reason_label"
                            :dot="false"
                        />
                        <span class="truncate text-xs text-slate-500 dark:text-slate-400">{{ $entry->method }} {{ $entry->path }}</span>
                    </span>
                    <time class="text-xs text-slate-400" title="{{ local_datetime($entry->created_at) }}">
                        {{ $entry->created_at->diffForHumans() }}
                    </time>
                </li>
            @empty
                <li class="px-4 py-8 text-center text-sm text-slate-400 sm:px-5">Nothing has been blocked yet.</li>
            @endforelse
        </ul>
    </x-admin.card>
</div>
