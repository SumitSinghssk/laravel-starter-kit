<x-admin :breadcrumb="[
    ['label' => 'Redirects', 'url' => route('admin.redirects.index')],
    ['label' => 'Create Redirect']
]">
    <x-admin.form-page
        :action="route('admin.redirects.store')"
        title="New redirect"
        description="Point an old or changed URL to its new address."
        :back="route('admin.redirects.index')"
        submit="Create redirect"
        submitting="Creating redirect…"
    >
        @include('admin.redirects.partials.form')
    </x-admin.form-page>
</x-admin>
