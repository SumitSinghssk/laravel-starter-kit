<x-admin>
    @if ($valid)
        <x-admin.auth-shell heading="Choose a new password" intro="You'll be signed out on every device and can then sign in with the new password.">
            <div class="rounded-2xl border border-slate-200/80 bg-white p-6 shadow-sm sm:p-7 dark:border-slate-800 dark:bg-slate-900">
                <form
                    method="POST"
                    action="{{ route('admin.password.update') }}"
                    class="space-y-5"
                    x-data="{ submitting: false }"
                    x-on:submit="submitting = true"
                >
                    @csrf
                    <input type="hidden" name="token" value="{{ $token }}" />

                    <input type="hidden" name="email" value="{{ $email }}" autocomplete="username" />
                    <div class="flex items-center gap-2.5 rounded-lg bg-slate-50 px-3 py-2.5 text-sm dark:bg-slate-800/60">
                        <x-admin.icon name="user" class="h-4 w-4 shrink-0 text-slate-400" />
                        <span class="min-w-0 truncate text-slate-700 dark:text-slate-200">{{ $email }}</span>
                    </div>
                    @error('email')
                        <p class="-mt-3 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror

                    <x-admin.form.input
                        type="password"
                        name="password"
                        label="New password"
                        placeholder="At least 8 characters"
                        autocomplete="new-password"
                        required
                        autofocus
                    />

                    <x-admin.form.input
                        type="password"
                        name="password_confirmation"
                        label="Confirm new password"
                        placeholder="Type it again"
                        autocomplete="new-password"
                        required
                    />

                    <x-admin.button full size="lg">
                        <span x-text="submitting ? 'Saving…' : 'Save new password'">Save new password</span>
                    </x-admin.button>
                </form>
            </div>
        </x-admin.auth-shell>
    @else
        <x-admin.auth-shell heading="This link doesn't work" intro="Reset links work once and expire after a while.">
            <div
                class="rounded-2xl border border-slate-200/80 bg-white p-6 text-center shadow-sm sm:p-7 dark:border-slate-800 dark:bg-slate-900"
                role="alert"
            >
                <span
                    class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-amber-50 text-amber-600 dark:bg-amber-500/10 dark:text-amber-400"
                >
                    <x-admin.icon name="key" class="h-5 w-5" />
                </span>
                <p class="mt-5 text-sm leading-relaxed text-slate-500 dark:text-slate-400">
                    This reset link was already used, has expired, or is incomplete. Ask for a new one and use the latest email.
                </p>
                <x-admin.button :href="route('admin.password.request')" class="mt-5" full>Get a new link</x-admin.button>
            </div>
        </x-admin.auth-shell>
    @endif
</x-admin>
