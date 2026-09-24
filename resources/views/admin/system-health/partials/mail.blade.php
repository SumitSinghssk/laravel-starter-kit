<div
    class="border-t border-slate-100 px-4 py-4 sm:px-5 dark:border-slate-800"
    x-data="{
        checking: false,
        result: null,
        async check() {
            this.checking = true
            this.result = null
            try {
                const response = await fetch(
                    {{ \Illuminate\Support\Js::from(route('admin.system-health.mail-check')) }},
                    {
                        method: 'POST',
                        headers: {
                            Accept: 'application/json',
                            'X-CSRF-TOKEN': document.querySelector(
                                'meta[name=csrf-token]',
                            ).content,
                        },
                    },
                )
                this.result =
                    response.status === 429
                        ? {
                              ok: false,
                              message:
                                  'Too many checks in a short time. Wait a minute and try again.',
                          }
                        : await response.json()
            } catch {
                this.result = {
                    ok: false,
                    message: 'The check could not reach the server. Try again.',
                }
            } finally {
                this.checking = false
            }
        },
    }"
>
    <div class="flex flex-wrap items-center gap-2">
        @if ($extra['smtp'])
            <x-admin.button type="button" size="sm" variant="secondary" icon="wifi" x-on:click="check()" x-bind:disabled="checking">
                <span x-text="checking ? 'Connecting…' : 'Check connection'">Check connection</span>
            </x-admin.button>
        @endif

        @if ($canEmailSettings)
            <x-admin.button :href="route('admin.settings.index', ['tab' => 'email'])" size="sm" variant="secondary" icon="send">
                {{ $extra['smtp'] ? 'Send a test email' : 'Set up email' }}
            </x-admin.button>
        @endif
    </div>
    @if ($extra['smtp'])
        <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">Connects and logs in to the mail server without sending anything.</p>
    @endif

    <div
        x-show="result"
        x-cloak
        x-bind:class="
            result?.ok
                ? 'border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-200'
                : 'border-red-200 bg-red-50 text-red-800 dark:border-red-500/30 dark:bg-red-500/10 dark:text-red-200'
        "
        class="mt-3 rounded-lg border px-3 py-2.5 text-sm"
        role="status"
    >
        <p x-text="result?.message"></p>
        <details x-show="result?.detail" class="mt-1.5 text-xs opacity-80">
            <summary class="cursor-pointer">What the mail server said</summary>
            <p class="mt-1 font-mono wrap-break-word" x-text="result?.detail"></p>
        </details>
    </div>
</div>
