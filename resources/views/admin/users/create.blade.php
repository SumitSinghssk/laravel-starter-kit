<x-admin :breadcrumb="[
    ['label' => 'Users', 'url' => route('admin.users.index')],
    ['label' => 'Create User']
]">
    <x-admin.form-page
        :action="route('admin.users.store')"
        upload
        title="New user"
        description="Add a team member, then give them roles or individual permissions."
        :back="route('admin.users.index')"
        submit="Create user"
        submitting="Creating user…"
    >
        @include('admin.users.partials.form')
    </x-admin.form-page>
</x-admin>
