<x-admin>
    <x-admin.auth-shell heading="Forgot your password?" intro="Enter your account email and we'll send you a link to choose a new one.">
        @if (session('reset_link_sent'))
            <div
                class="rounded-2xl border border-slate-200/80 bg-white p-6 text-center shadow-sm sm:p-7 dark:border-slate-800 dark:bg-slate-900"
                role="status"
            >
                <span
                    class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-400"
                >
                    <x-admin.icon name="mail" class="h-5 w-5" />
                </span>
                <h2 class="mt-5 text-base font-semibold text-slate-900 dark:text-white">Check your email</h2>
                <p class="mt-2 text-sm leading-relaxed text-slate-500 dark:text-slate-400">
                    If an admin account uses
                    <span class="font-medium text-slate-800 dark:text-slate-200">{{ session('reset_link_sent') }}</span>
                    , a reset link is on its way. It expires in {{ $minutes }} minutes.
                </p>
                <p class="mt-3 text-xs text-slate-400 dark:text-slate-500">Nothing after a few minutes? Check the spam folder, or try again.</p>
                <form
                    method="POST"
                    action="{{ route('admin.password.email') }}"
                    class="mt-5"
                    x-data="{ submitting: false }"
                    x-on:submit="submitting = true"
                >
                    @csrf
                    <input type="hidden" name="email" value="{{ session('reset_link_sent') }}" />
                    <x-admin.button variant="secondary" full>
                        <span x-text="submitting ? 'Sending…' : 'Send the link again'">Send the link again</span>
                    </x-admin.button>
                </form>
            </div>
        @else
            @unless ($canDeliver)
                <div
                    class="mb-4 flex items-start gap-2.5 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-200"
                >
                    <x-admin.icon name="alert-triangle" class="mt-0.5 h-4 w-4 shrink-0" />
                    <span>
                        Email isn't set up on this site yet, so reset links can't be delivered. Ask another administrator to set a new password for
                        you.
                    </span>
                </div>
            @endunless

            <div class="rounded-2xl border border-slate-200/80 bg-white p-6 shadow-sm sm:p-7 dark:border-slate-800 dark:bg-slate-900">
                <form
                    method="POST"
                    action="{{ route('admin.password.email') }}"
                    class="space-y-5"
                    x-data="{ submitting: false }"
                    x-on:submit="submitting = true"
                >
                    @csrf

                    <x-admin.form.input
                        type="email"
                        name="email"
                        label="Email address"
                        placeholder="name@company.com"
                        autocomplete="username"
                        required
                        autofocus
                    />

                    <x-admin.button full size="lg">
                        <span x-text="submitting ? 'Sending…' : 'Send reset link'">Send reset link</span>
                    </x-admin.button>
                </form>
            </div>
        @endif
    </x-admin.auth-shell>
</x-admin>
