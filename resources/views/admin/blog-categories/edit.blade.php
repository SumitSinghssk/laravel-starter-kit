<x-admin :breadcrumb="[
    ['label' => 'Blog Categories', 'url' => route('admin.blog-categories.index')],
    ['label' => 'Edit Category']
]">
    <x-admin.form-page
        :action="route('admin.blog-categories.update', $blogCategory->id)"
        method="PUT"
        upload
        title="Edit blog category"
        :description="$blogCategory->name"
        :back="route('admin.blog-categories.index')"
        submit="Save changes"
        submitting="Saving…"
    >
        <x-slot:actions>
            @if ($blogCategory->seo)
                @can('admin.seo.edit')
                    <x-admin.button variant="secondary" :href="route('admin.seo.edit', $blogCategory->seo->id)" icon="globe">
                        Edit SEO
                    </x-admin.button>
                @endcan
            @else
                @can('admin.seo.create')
                    <x-admin.button
                        variant="secondary"
                        :href="route('admin.seo.create', ['model_type' => 'blog_category', 'model_id' => $blogCategory->id])"
                        icon="globe"
                    >
                        Add SEO
                    </x-admin.button>
                @endcan
            @endif

            @can('admin.blog-categories.delete')
                <x-admin.delete-button
                    message="It will be moved to the Trash, where it can be restored."
                    :route="route('admin.blog-categories.destroy', $blogCategory)"
                    title="Delete this category?"
                />
            @endcan
        </x-slot>

        @include('admin.blog-categories.partials.form')
    </x-admin.form-page>
</x-admin>
