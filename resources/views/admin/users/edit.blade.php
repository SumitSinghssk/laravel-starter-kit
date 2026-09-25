<x-admin :breadcrumb="[
    ['label' => 'Users', 'url' => route('admin.users.index')],
    ['label' => 'Edit User']
]">
    <x-admin.form-page
        :action="route('admin.users.update', $user)"
        method="PUT"
        upload
        title="Edit user"
        :description="$user->name . ' · ' . $user->email"
        :back="route('admin.users.index')"
        submit="Save changes"
        submitting="Saving…"
    >
        @can('admin.users.delete')
            @if ($user->id !== auth()->id())
                <x-slot:actions>
                    <x-admin.delete-button
                        message="It will be moved to the Trash, where it can be restored."
                        :route="route('admin.users.destroy', $user)"
                        title="Delete this user?"
                    />
                </x-slot>
            @endif
        @endcan

        @include('admin.users.partials.form')
    </x-admin.form-page>

    @can('admin.users.sessions')
        @php($userSessions = app(\App\Services\ActiveSessions::class)->forUser($user,request()->session()->getId(),))
        <x-admin.card
            class="mt-6"
            title="Where {{ $user->is(auth()->user()) ? 'you are' : $user->name . ' is' }} signed in"
            text="Signing out ends the session straight away and stops “remember me” on every device."
            icon="monitor"
        >
            @if ($userSessions->where('is_current', false)->isNotEmpty())
                <x-slot:actions>
                    <x-admin.confirm-button
                        :action="route('admin.users.sessions.destroy-all', $user)"
                        method="DELETE"
                        icon="log-out"
                        variant="danger-outline"
                        :title="$user->is(auth()->user()) ? 'Sign out of all your other devices?' : 'Sign ' . $user->name . ' out everywhere?'"
                        :message="$user->is(auth()->user()) ? 'This browser stays signed in.' : $user->name . ' is signed out of every device and has to sign in again.'"
                        confirm="Sign out"
                    >
                        {{ $user->is(auth()->user()) ? 'Sign out other devices' : 'Sign out everywhere' }}
                    </x-admin.confirm-button>
                </x-slot>
            @endif

            @include(
                'admin.partials.sessions-list',
                [
                    'sessions' => $userSessions,
                    'endUrl' => fn ($key) => route('admin.users.sessions.destroy', [$user, $key]),
                    'confirm' => 'modal',
                ]
            )
        </x-admin.card>
    @endcan

    @php($isLocked = app(\App\Services\AccountLockout::class)->isLocked($user))

    <x-admin.card class="mt-6" title="Sign-in status" icon="lock">
        @if ($isLocked)
            @can('admin.users.unlock')
                <x-slot:actions>
                    <form method="POST" action="{{ route('admin.users.unlock', $user) }}">
                        @csrf
                        <x-admin.button size="sm" icon="check">Unlock account</x-admin.button>
                    </form>
                </x-slot>
            @endcan
        @endif

        <div class="flex items-center gap-3">
            <span
                @class([
                    'flex h-10 w-10 shrink-0 items-center justify-center rounded-full',
                    'bg-red-50 text-red-600 dark:bg-red-500/10 dark:text-red-400' => $isLocked,
                    'bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-400' => ! $isLocked,
                ])
            >
                <x-admin.icon :name="$isLocked ? 'lock' : 'check-circle'" class="h-4.5 w-4.5" />
            </span>
            <div class="text-sm">
                <p class="font-medium text-slate-900 dark:text-white">
                    {{ $isLocked ? 'Locked since ' . local_datetime($user->locked_at) : 'Can sign in' }}
                </p>
                <p class="text-xs text-slate-500 dark:text-slate-400">
                    @if ($isLocked)
                        {{ $user->locked_until ? 'Unlocks by itself at ' . local_datetime($user->locked_until) . '.' : 'Stays locked until someone unlocks it.' }}
                        Resetting the password also unlocks it.
                    @elseif ($user->failed_logins > 0)
                        {{ $user->failed_logins }} failed {{ str('attempt')->plural($user->failed_logins) }} since the last successful sign-in.
                    @else
                        No failed sign-in attempts.
                    @endif
                </p>
            </div>
        </div>
    </x-admin.card>

    <x-admin.card class="mt-6" title="Two-factor sign-in" icon="shield-check">
        @if ($user->hasTwoFactor() && ! $user->is(auth()->user()))
            @can('admin.users.two-factor')
                <x-slot:actions>
                    <x-admin.confirm-button
                        :action="route('admin.users.two-factor.reset', $user)"
                        method="DELETE"
                        :title="'Reset two-factor sign-in for ' . $user->name . '?'"
                        message="Do this when they lost their phone and their recovery codes. They can sign in with just their password until they set it up again, and they get an email about it."
                        confirm="Reset"
                        icon="refresh"
                        size="sm"
                        variant="danger-outline"
                    >
                        Reset two-factor
                    </x-admin.confirm-button>
                </x-slot>
            @endcan
        @endif

        <div class="flex items-center gap-3">
            <span
                @class([
                    'flex h-10 w-10 shrink-0 items-center justify-center rounded-full',
                    'bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-400' => $user->hasTwoFactor(),
                    'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400' => ! $user->hasTwoFactor(),
                ])
            >
                <x-admin.icon :name="$user->hasTwoFactor() ? 'shield-check' : 'shield'" class="h-4.5 w-4.5" />
            </span>
            <div class="text-sm">
                <p class="font-medium text-slate-900 dark:text-white">
                    {{ $user->hasTwoFactor() ? 'On since ' . local_date($user->two_factor_confirmed_at) : 'Off' }}
                </p>
                <p class="text-xs text-slate-500 dark:text-slate-400">
                    @if ($user->requiresTwoFactor())
                        Required by their role{{ $user->hasTwoFactor() ? '' : ': they will be asked to set it up at their next page view' }}.
                    @elseif ($user->is(auth()->user()))
                        <a href="{{ route('admin.two-factor.show') }}" class="font-medium text-blue-600 hover:underline dark:text-blue-400">
                            Manage your own two-factor sign-in
                        </a>
                    @else
                        Optional for their role. Require it on the Roles page.
                    @endif
                </p>
            </div>
        </div>
    </x-admin.card>
</x-admin>
