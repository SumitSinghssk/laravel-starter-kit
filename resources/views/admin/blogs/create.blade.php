<x-admin :breadcrumb="[
    ['label' => 'Blogs', 'url' => route('admin.blogs.index')],
    ['label' => 'Create Post']
]">
    <x-admin.form-page
        :action="route('admin.blogs.store')"
        upload
        title="New blog post"
        description="Write a post, pick its categories and choose when it goes live."
        :back="route('admin.blogs.index')"
        submit="Create post"
        submitting="Creating post…"
    >
        @include('admin.blogs.partials.form')
    </x-admin.form-page>
</x-admin>
