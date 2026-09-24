<x-admin>
    <x-admin.auth-shell heading="Two-factor sign-in" intro="One more step to keep your account safe.">
        <div
            class="rounded-2xl border border-slate-200/80 bg-white p-6 shadow-sm sm:p-7 dark:border-slate-800 dark:bg-slate-900"
            x-data="{
                recovery:
                    {{ \Illuminate\Support\Js::from((bool) old('recovery', $errors->has('recovery_code'))) }},
                code: '',
                submitting: false,
                toggle() {
                    this.recovery = ! this.recovery
                    this.$nextTick(() =>
                        (this.recovery
                            ? this.$refs.recoveryInput
                            : this.$refs.codeInput
                        )?.focus(),
                    )
                },
                typed() {
                    this.code = this.code.replace(/\D+/g, '').slice(0, 6)
                    if (this.code.length === 6 && ! this.submitting) {
                        this.submitting = true
                        this.$nextTick(() => this.$refs.form.requestSubmit())
                    }
                },
            }"
        >
            <div class="mb-5 flex items-start gap-3">
                <span
                    class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-blue-50 text-blue-600 dark:bg-blue-500/10 dark:text-blue-300"
                >
                    <x-admin.icon name="smartphone" class="h-5 w-5" x-show="! recovery" />
                    <x-admin.icon name="key" class="h-5 w-5" x-show="recovery" x-cloak />
                </span>
                <p class="text-sm leading-relaxed text-slate-600 dark:text-slate-300">
                    <span x-show="! recovery">
                        Open your authenticator app and enter the 6-digit code it shows for
                        <span class="font-medium text-slate-900 dark:text-white">{{ $email }}</span>
                    </span>
                    <span x-show="recovery" x-cloak>
                        Lost your phone? Enter one of the recovery codes you saved when you set up two-factor sign-in.
                    </span>
                </p>
            </div>

            <form method="POST" action="{{ route('admin.two-factor.verify') }}" class="space-y-5" x-ref="form" x-on:submit="submitting = true">
                @csrf
                <input
                    type="hidden"
                    name="recovery"
                    x-bind:value="recovery ? 1 : 0"
                    value="{{ old('recovery', $errors->has('recovery_code')) ? 1 : 0 }}"
                />

                <div x-show="! recovery">
                    <label for="code" class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-200">Authentication code</label>
                    <input
                        id="code"
                        name="code"
                        x-ref="codeInput"
                        x-model="code"
                        x-on:input="typed()"
                        x-bind:disabled="recovery"
                        type="text"
                        inputmode="numeric"
                        autocomplete="one-time-code"
                        maxlength="7"
                        placeholder="000000"
                        autofocus
                        @if ($errors->has('code')) aria-invalid="true" aria-describedby="code-error" @endif
                        @class([
                            'block h-14 w-full rounded-xl border bg-white text-center font-mono text-2xl tracking-[0.5em] text-slate-900 shadow-xs transition outline-none placeholder:text-slate-300 focus:ring-3 dark:bg-slate-900 dark:text-white dark:placeholder:text-slate-600',
                            'border-red-400 focus:border-red-400 focus:ring-red-500/20' => $errors->has('code'),
                            'border-slate-200 focus:border-blue-400 focus:ring-blue-500/20 dark:border-slate-700' => ! $errors->has('code'),
                        ])
                    />
                    @error('code')
                        <p id="code-error" class="mt-1.5 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <div x-show="recovery" x-cloak>
                    <label for="recovery_code" class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-200">Recovery code</label>
                    <input
                        id="recovery_code"
                        name="recovery_code"
                        x-ref="recoveryInput"
                        x-bind:disabled="! recovery"
                        type="text"
                        autocomplete="off"
                        spellcheck="false"
                        placeholder="abcde-12345"
                        @if ($errors->has('recovery_code')) aria-invalid="true" aria-describedby="recovery-error" @endif
                        @class([
                            'block h-11 w-full rounded-lg border bg-white px-3 font-mono text-base text-slate-900 shadow-xs transition outline-none placeholder:text-slate-300 focus:ring-3 dark:bg-slate-900 dark:text-white dark:placeholder:text-slate-600',
                            'border-red-400 focus:border-red-400 focus:ring-red-500/20' => $errors->has('recovery_code'),
                            'border-slate-200 focus:border-blue-400 focus:ring-blue-500/20 dark:border-slate-700' => ! $errors->has('recovery_code'),
                        ])
                    />
                    @error('recovery_code')
                        <p id="recovery-error" class="mt-1.5 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror

                    <p class="mt-1.5 text-xs text-slate-400">
                        Each code works once. {{ $codesLeft }} {{ \Illuminate\Support\Str::plural('code', $codesLeft) }} left.
                    </p>
                </div>

                <x-admin.form.checkbox name="trust" :label="'Trust this device for ' . $trustDays . ' days'" :checked="old('trust')" />

                <x-admin.button full size="lg" x-bind:disabled="submitting">
                    <span x-text="submitting ? 'Checking…' : 'Verify and sign in'">Verify and sign in</span>
                </x-admin.button>
            </form>

            <button
                type="button"
                x-on:click="toggle()"
                class="mt-4 w-full cursor-pointer text-center text-sm font-medium text-blue-600 hover:underline dark:text-blue-400"
            >
                <span x-show="! recovery">Use a recovery code instead</span>
                <span x-show="recovery" x-cloak>Use a code from my app instead</span>
            </button>
        </div>
    </x-admin.auth-shell>
</x-admin>
