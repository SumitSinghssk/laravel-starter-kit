<x-admin :breadcrumb="[
    ['label' => 'Testimonials', 'url' => route('admin.testimonials.index')],
    ['label' => 'Edit Testimonial']
]">
    <x-admin.form-page
        :action="route('admin.testimonials.update', $testimonial)"
        method="PUT"
        upload
        title="Edit testimonial"
        :description="$testimonial->name"
        :back="route('admin.testimonials.index')"
        submit="Save changes"
        submitting="Saving…"
    >
        <x-slot:actions>
            @can('admin.testimonials.delete')
                <x-admin.delete-button
                    message="It will be moved to the Trash, where it can be restored."
                    :route="route('admin.testimonials.destroy', $testimonial)"
                    title="Delete this testimonial?"
                />
            @endcan
        </x-slot>

        @include('admin.testimonials.partials.form')
    </x-admin.form-page>
</x-admin>
