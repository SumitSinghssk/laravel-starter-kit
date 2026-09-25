@php
    use Illuminate\Support\Js;
    use Illuminate\Support\Str;

    $appName = \App\Helpers\Settings::appName();
    $actions = [
        'start' => ['url' => route('admin.two-factor.start'), 'method' => 'POST', 'title' => 'Set up with a new app?', 'text' => 'Your current codes keep working until you finish the new setup.', 'button' => 'Continue', 'danger' => false],
        'codes' => ['url' => route('admin.two-factor.recovery-codes'), 'method' => 'POST', 'title' => 'Make new recovery codes?', 'text' => 'Your old recovery codes stop working straight away.', 'button' => 'Make new codes', 'danger' => false],
        'off' => ['url' => route('admin.two-factor.destroy'), 'method' => 'DELETE', 'title' => 'Turn off two-factor sign-in?', 'text' => 'Anyone with your password could then sign in to your account.', 'button' => 'Turn off', 'danger' => true],
    ];
    $openAction = $errors->twoFactor->has('password') ? old('_action') : null;
@endphp

<x-admin :breadcrumb="[['label' => 'Two-factor sign-in', 'url' => route('admin.two-factor.show')]]">
    <x-admin.page-header
        title="Two-factor sign-in"
        description="Ask for a code from your phone as well as your password, so a stolen password isn't enough to get in."
        icon="shield-check"
    />

    <div
        class="max-w-3xl space-y-6"
        x-data="{
            actions: {{ Js::from($actions) }},
            current: null,
            ask(key) {
                this.current = key
                this.$dispatch('open-modal', 'two-factor-password')
                this.$nextTick(() =>
                    setTimeout(
                        () => document.getElementById('two-factor-password')?.focus(),
                        50,
                    ),
                )
            },
        }"
        @if ($openAction) x-init="ask({{ Js::from($openAction) }})" @endif
    >
        @if ($required && ! $enabled)
            <div
                class="flex items-start gap-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3.5 text-sm text-amber-800 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-200"
                role="alert"
            >
                <x-admin.icon name="alert-triangle" class="mt-0.5 h-4 w-4 shrink-0" />
                <span>
                    <span class="font-semibold">Your role requires two-factor sign-in.</span>
                    Set it up below to keep using the admin. It takes about a minute.
                </span>
            </div>
        @endif

        @if ($newCodes)
            <x-admin.card id="codes" title="Save your recovery codes" icon="key" class="scroll-mt-4 border-blue-300 dark:border-blue-500/40">
                <p class="text-sm text-slate-600 dark:text-slate-300">
                    If you lose your phone, each code lets you sign in once. Keep them somewhere safe, like a password manager.
                    <span class="font-medium text-slate-900 dark:text-white">They won't be shown again.</span>
                </p>

                <ol
                    class="mt-4 grid grid-cols-2 gap-2 rounded-xl bg-slate-50 p-4 font-mono text-sm text-slate-800 sm:grid-cols-4 dark:bg-slate-800/60 dark:text-slate-100"
                >
                    @foreach ($newCodes as $code)
                        <li class="rounded-md bg-white px-2 py-1.5 text-center shadow-xs dark:bg-slate-900">{{ $code }}</li>
                    @endforeach
                </ol>

                <div
                    class="mt-4 flex flex-wrap gap-2"
                    x-data="{
                        text: {{ Js::from($appName . ' recovery codes for ' . $user->email . "\n\n" . implode("\n", $newCodes) . "\n\nEach code works once.") }},
                        copied: false,
                        copy() {
                            window.copyText(this.text).then((ok) => {
                                this.copied = ok
                                setTimeout(() => (this.copied = false), 1800)
                            })
                        },
                        download() {
                            const link = document.createElement('a')
                            link.href = URL.createObjectURL(
                                new Blob([this.text], { type: 'text/plain' }),
                            )
                            link.download =
                                {{ Js::from(Str::slug($appName) . '-recovery-codes.txt') }}
                            link.click()
                            URL.revokeObjectURL(link.href)
                        },
                    }"
                >
                    <x-admin.button type="button" variant="secondary" size="sm" x-on:click="copy()">
                        <x-slot:leftIcon>
                            <x-admin.icon name="copy" class="h-3.5 w-3.5" x-show="! copied" />
                            <x-admin.icon name="check" class="h-3.5 w-3.5 text-emerald-500" x-show="copied" x-cloak />
                        </x-slot>
                        <span x-text="copied ? 'Copied' : 'Copy codes'">Copy codes</span>
                    </x-admin.button>
                    <x-admin.button type="button" variant="secondary" size="sm" icon="download" x-on:click="download()">Download .txt</x-admin.button>
                    <x-admin.button type="button" variant="secondary" size="sm" icon="file-text" x-on:click="window.print()">Print</x-admin.button>
                </div>
            </x-admin.card>
        @endif

        @if ($setup)
            <x-admin.card id="setup" title="Set up your authenticator app" icon="smartphone" class="scroll-mt-4">
                <ol class="space-y-6">
                    <li class="flex gap-4">
                        <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-blue-600 text-xs font-semibold text-white">
                            1
                        </span>
                        <div class="min-w-0 text-sm text-slate-600 dark:text-slate-300">
                            <p class="font-medium text-slate-900 dark:text-white">Get an authenticator app</p>
                            <p class="mt-0.5">
                                Any of these free apps works: Google Authenticator, Microsoft Authenticator, Authy or 1Password. No account is needed.
                            </p>
                        </div>
                    </li>

                    <li class="flex gap-4">
                        <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-blue-600 text-xs font-semibold text-white">
                            2
                        </span>
                        <div class="min-w-0 flex-1 text-sm text-slate-600 dark:text-slate-300">
                            <p class="font-medium text-slate-900 dark:text-white">Scan this code with the app</p>
                            <div class="mt-3 flex flex-col gap-4 sm:flex-row sm:items-center">
                                <div
                                    class="w-fit shrink-0 rounded-xl border border-slate-200 bg-white p-3 [&_svg]:h-44 [&_svg]:w-44"
                                    role="img"
                                    aria-label="QR code for your authenticator app"
                                >
                                    {!! $setup['qr'] !!}
                                </div>
                                <div class="min-w-0" x-data="{ copied: false }">
                                    <p class="text-xs text-slate-500 dark:text-slate-400">Can't scan it? Type this key into the app instead:</p>
                                    <div class="mt-1.5 flex items-center gap-2">
                                        <code
                                            class="rounded-lg bg-slate-100 px-2.5 py-1.5 font-mono text-sm tracking-wider break-all text-slate-800 dark:bg-slate-800 dark:text-slate-100"
                                        >
                                            {{ $setup['key'] }}
                                        </code>
                                        <button
                                            type="button"
                                            x-on:click="
                                                window
                                                    .copyText({{ Js::from(str_replace(' ', '', $setup['key'])) }})
                                                    .then((ok) => {
                                                        copied = ok
                                                        setTimeout(() => (copied = false), 1500)
                                                    })
                                            "
                                            class="shrink-0 cursor-pointer rounded-md p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-slate-800"
                                            aria-label="Copy key"
                                        >
                                            <x-admin.icon name="copy" class="h-4 w-4" x-show="! copied" />
                                            <x-admin.icon name="check" class="h-4 w-4 text-emerald-500" x-show="copied" x-cloak />
                                        </button>
                                    </div>
                                    <p class="mt-2 text-xs text-slate-400">Account: {{ $user->email }} · Time-based</p>
                                    <a
                                        href="{{ $setup['url'] }}"
                                        class="mt-2 inline-flex text-xs font-medium text-blue-600 hover:underline sm:hidden dark:text-blue-400"
                                    >
                                        On this phone? Open in your authenticator app
                                    </a>
                                </div>
                            </div>
                        </div>
                    </li>

                    <li class="flex gap-4">
                        <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-blue-600 text-xs font-semibold text-white">
                            3
                        </span>
                        <div class="min-w-0 flex-1 text-sm">
                            <p class="font-medium text-slate-900 dark:text-white">Enter the 6-digit code it shows</p>
                            <form
                                method="POST"
                                action="{{ route('admin.two-factor.confirm') }}"
                                class="mt-3 flex flex-wrap items-start gap-2"
                                x-data="{ submitting: false }"
                                x-on:submit="submitting = true"
                            >
                                @csrf
                                <div>
                                    <label for="confirm-code" class="sr-only">6-digit code</label>
                                    <input
                                        id="confirm-code"
                                        name="code"
                                        type="text"
                                        inputmode="numeric"
                                        autocomplete="one-time-code"
                                        maxlength="7"
                                        placeholder="000000"
                                        autofocus
                                        @class([
                                            'h-11 w-40 rounded-lg border bg-white text-center font-mono text-lg tracking-[0.4em] text-slate-900 shadow-xs outline-none focus:ring-3 dark:bg-slate-900 dark:text-white',
                                            'border-red-400 focus:ring-red-500/20' => $errors->twoFactor->has('code'),
                                            'border-slate-200 focus:border-blue-400 focus:ring-blue-500/20 dark:border-slate-700' => ! $errors->twoFactor->has('code'),
                                        ])
                                    />
                                </div>
                                <x-admin.button size="lg" icon="check" x-bind:disabled="submitting">
                                    <span x-text="submitting ? 'Checking…' : 'Turn on'">Turn on</span>
                                </x-admin.button>
                            </form>
                            @if ($errors->twoFactor->has('code'))
                                <p class="mt-1.5 text-xs text-red-600 dark:text-red-400">{{ $errors->twoFactor->first('code') }}</p>
                            @endif
                        </div>
                    </li>
                </ol>

                <form
                    method="POST"
                    action="{{ route('admin.two-factor.cancel') }}"
                    class="mt-6 border-t border-slate-100 pt-4 dark:border-slate-800"
                >
                    @csrf
                    @method('DELETE')
                    <button
                        type="submit"
                        class="cursor-pointer text-sm text-slate-500 hover:text-slate-800 dark:text-slate-400 dark:hover:text-white"
                    >
                        Cancel setup
                    </button>
                </form>
            </x-admin.card>
        @endif

        @if ($enabled)
            <x-admin.card title="Status" icon="shield-check">
                <div class="flex flex-wrap items-center justify-between gap-4">
                    <div class="flex items-center gap-3">
                        <span
                            class="flex h-11 w-11 items-center justify-center rounded-full bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-400"
                        >
                            <x-admin.icon name="shield-check" class="h-5 w-5" />
                        </span>
                        <div>
                            <p class="text-sm font-semibold text-slate-900 dark:text-white">Two-factor sign-in is on</p>
                            <p class="text-xs text-slate-500 dark:text-slate-400">
                                Since {{ local_date($user->two_factor_confirmed_at) }}
                                @if ($required)
                                    · required by your role
                                @endif
                            </p>
                        </div>
                    </div>
                    @unless ($setup)
                        <x-admin.button type="button" variant="secondary" icon="smartphone" x-on:click="ask('start')">
                            Set up with a new phone
                        </x-admin.button>
                    @endunless
                </div>

                <dl class="mt-5 divide-y divide-slate-100 border-t border-slate-100 text-sm dark:divide-slate-800 dark:border-slate-800">
                    <div class="flex flex-wrap items-center justify-between gap-3 py-3">
                        <dt>
                            <span class="font-medium text-slate-800 dark:text-slate-100">Recovery codes</span>
                            <span
                                @class(['block text-xs', 'text-red-600 dark:text-red-400' => $codesLeft <= 2, 'text-slate-500 dark:text-slate-400' => $codesLeft > 2])
                            >
                                {{ $codesLeft }} of {{ \App\Services\TwoFactor::RECOVERY_CODES }}
                                left{{ $codesLeft <= 2 ? '. Make new ones soon.' : '' }}
                            </span>
                        </dt>
                        <dd>
                            <x-admin.button type="button" variant="secondary" size="sm" icon="refresh" x-on:click="ask('codes')">
                                Make new codes
                            </x-admin.button>
                        </dd>
                    </div>

                    <div class="flex flex-wrap items-center justify-between gap-3 py-3">
                        <dt>
                            <span class="font-medium text-slate-800 dark:text-slate-100">This browser</span>
                            <span class="block text-xs text-slate-500 dark:text-slate-400">
                                {{ $trusted ? 'Trusted: no code needed here for up to ' . \App\Services\TwoFactor::TRUST_DAYS . ' days.' : 'Asks for a code at every sign-in.' }}
                            </span>
                        </dt>
                        @if ($trusted)
                            <dd>
                                <form method="POST" action="{{ route('admin.two-factor.forget-device') }}">
                                    @csrf
                                    @method('DELETE')
                                    <x-admin.button variant="secondary" size="sm" icon="x">Stop trusting</x-admin.button>
                                </form>
                            </dd>
                        @endif
                    </div>

                    <div class="flex flex-wrap items-center justify-between gap-3 py-3">
                        <dt>
                            <span class="font-medium text-slate-800 dark:text-slate-100">Turn off</span>
                            <span class="block text-xs text-slate-500 dark:text-slate-400">
                                {{ $required ? 'Not possible: your role requires two-factor sign-in.' : 'Only your password will be needed to sign in.' }}
                            </span>
                        </dt>
                        @unless ($required)
                            <dd>
                                <x-admin.button type="button" variant="danger-outline" size="sm" icon="x" x-on:click="ask('off')">
                                    Turn off
                                </x-admin.button>
                            </dd>
                        @endunless
                    </div>
                </dl>
            </x-admin.card>
        @elseif (! $setup)
            <x-admin.card title="Status" icon="shield">
                <div class="flex flex-col gap-5 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex items-start gap-3">
                        <span
                            class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400"
                        >
                            <x-admin.icon name="shield" class="h-5 w-5" />
                        </span>
                        <div>
                            <p class="text-sm font-semibold text-slate-900 dark:text-white">Two-factor sign-in is off</p>
                            <p class="mt-0.5 max-w-md text-xs leading-relaxed text-slate-500 dark:text-slate-400">
                                Turn it on and you'll type a 6-digit code from a free phone app after your password. You can trust your own computer
                                for {{ \App\Services\TwoFactor::TRUST_DAYS }} days at a time.
                            </p>
                        </div>
                    </div>
                    <form method="POST" action="{{ route('admin.two-factor.start') }}" class="shrink-0">
                        @csrf
                        <x-admin.button icon="shield-check">Set up two-factor</x-admin.button>
                    </form>
                </div>
            </x-admin.card>
        @endif

        <x-modal name="two-factor-password" maxWidth="md">
            <form method="POST" x-bind:action="actions[current]?.url" x-data="{ busy: false }" x-on:submit="busy = true" class="p-6">
                @csrf
                <input type="hidden" name="_method" x-bind:value="actions[current]?.method ?? 'POST'" />
                <input type="hidden" name="_action" x-bind:value="current" />

                <h2 class="text-base font-semibold text-slate-900 dark:text-white" x-text="actions[current]?.title"></h2>
                <p class="mt-1.5 text-sm text-slate-500 dark:text-slate-400" x-text="actions[current]?.text"></p>

                <div class="mt-4">
                    <x-admin.form.input
                        type="password"
                        name="password"
                        id="two-factor-password"
                        label="Your password"
                        autocomplete="current-password"
                        :error="$errors->twoFactor->first('password')"
                        required
                    />
                </div>

                <div class="mt-6 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                    <x-admin.button type="button" variant="secondary" x-on:click="$dispatch('close-modal', 'two-factor-password')">
                        Cancel
                    </x-admin.button>
                    <button
                        type="submit"
                        x-bind:disabled="busy"
                        x-bind:class="
                            actions[current]?.danger
                                ? 'bg-red-600 hover:bg-red-700'
                                : 'bg-blue-600 hover:bg-blue-700'
                        "
                        class="inline-flex h-9 cursor-pointer items-center justify-center rounded-lg px-3.5 text-sm font-semibold text-white shadow-xs transition disabled:opacity-50"
                    >
                        <span x-text="busy ? 'Working…' : actions[current]?.button"></span>
                    </button>
                </div>
            </form>
        </x-modal>
    </div>
</x-admin>
