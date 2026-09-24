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
</x-admin>
