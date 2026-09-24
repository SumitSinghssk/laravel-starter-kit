<x-admin :breadcrumb="[
    ['label' => 'SEO', 'url' => route('admin.seo.index')],
    ['label' => 'Edit SEO']
]">
    <x-admin.form-page
        :action="route('admin.seo.update', $seo->id)"
        method="PUT"
        upload
        title="Edit SEO"
        :description="$seo->page . ' · /' . ltrim($seo->slug, '/')"
        :back="route('admin.seo.index')"
        submit="Save changes"
        submitting="Saving…"
    >
        <x-slot:actions>
            <x-admin.button variant="secondary" :href="url(ltrim($seo->slug, '/'))" target="_blank" rel="noopener" icon="external-link">
                View page
            </x-admin.button>

            @can('admin.seo.delete')
                <x-admin.delete-button :route="route('admin.seo.destroy', $seo)" title="Delete this SEO entry?" />
            @endcan
        </x-slot>

        @include('admin.seo.partials.form')
    </x-admin.form-page>
</x-admin>
