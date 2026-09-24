<x-admin :breadcrumb="[
    ['label' => 'Pages', 'url' => route('admin.pages.index')],
    ['label' => 'Create Page']
]">
    <x-admin.form-page
        :action="route('admin.pages.store')"
        upload
        title="New page"
        description="Write and publish a new page, like a privacy policy or terms of service."
        :back="route('admin.pages.index')"
        submit="Create page"
        submitting="Creating page…"
    >
        @include('admin.pages.partials.form')
    </x-admin.form-page>
</x-admin>
