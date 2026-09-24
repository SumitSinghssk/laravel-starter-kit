<x-admin :breadcrumb="[
    ['label' => 'Blog Categories', 'url' => route('admin.blog-categories.index')],
    ['label' => 'Create Category']
]">
    <x-admin.form-page
        :action="route('admin.blog-categories.store')"
        upload
        title="New blog category"
        description="Add a new category to organise your blog posts."
        :back="route('admin.blog-categories.index')"
        submit="Create category"
        submitting="Creating category…"
    >
        @include('admin.blog-categories.partials.form')
    </x-admin.form-page>
</x-admin>
