<x-admin :breadcrumb="[
    ['label' => 'Blogs', 'url' => route('admin.blogs.index')],
    ['label' => 'Edit Post']
]">
    <x-admin.form-page
        :action="route('admin.blogs.update', $blog->id)"
        method="PUT"
        upload
        title="Edit post"
        :description="$blog->title"
        :back="route('admin.blogs.index')"
        submit="Save changes"
        submitting="Saving…"
    >
        <x-slot:actions>
            @if ($blog->seo)
                @can('admin.seo.edit')
                    <x-admin.button variant="secondary" :href="route('admin.seo.edit', $blog->seo->id)" icon="globe">Edit SEO</x-admin.button>
                @endcan
            @else
                @can('admin.seo.create')
                    <x-admin.button
                        variant="secondary"
                        :href="route('admin.seo.create', ['model_type' => 'blog', 'model_id' => $blog->id])"
                        icon="globe"
                    >
                        Add SEO
                    </x-admin.button>
                @endcan
            @endif

            @can('admin.blogs.delete')
                <x-admin.delete-button
                    message="It will be moved to the Trash, where it can be restored."
                    :route="route('admin.blogs.destroy', $blog)"
                    title="Delete this post?"
                />
            @endcan
        </x-slot>

        @include('admin.blogs.partials.form')
    </x-admin.form-page>
</x-admin>
