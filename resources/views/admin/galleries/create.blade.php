<x-admin :breadcrumb="[
    ['label' => 'Gallery', 'url' => route('admin.galleries.index')],
    ['label' => 'Create Album']
]">
    <x-admin.form-page
        :action="route('admin.galleries.store')"
        title="New album"
        description="Create the album first; you can add photos, videos and YouTube links on the next screen."
        :back="route('admin.galleries.index')"
        submit="Create album"
        submitting="Creating album…"
    >
        @include('admin.galleries.partials.form', ['gallery' => null])
    </x-admin.form-page>
</x-admin>
