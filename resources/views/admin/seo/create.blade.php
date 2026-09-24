<x-admin :breadcrumb="[
    ['label' => 'SEO', 'url' => route('admin.seo.index')],
    ['label' => 'Create SEO']
]">
    <x-admin.form-page
        :action="route('admin.seo.store')"
        upload
        title="New SEO entry"
        description="Control how a page appears in search results and when it is shared."
        :back="route('admin.seo.index')"
        submit="Create SEO"
        submitting="Creating SEO…"
    >
        @include('admin.seo.partials.form')
    </x-admin.form-page>
</x-admin>
