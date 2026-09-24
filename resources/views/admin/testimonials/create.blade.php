<x-admin :breadcrumb="[
    ['label' => 'Testimonials', 'url' => route('admin.testimonials.index')],
    ['label' => 'Create Testimonial']
]">
    <x-admin.form-page
        :action="route('admin.testimonials.store')"
        upload
        title="New testimonial"
        description="Add what a client said about working with you."
        :back="route('admin.testimonials.index')"
        submit="Create testimonial"
        submitting="Creating testimonial…"
    >
        @include('admin.testimonials.partials.form', ['testimonial' => null])
    </x-admin.form-page>
</x-admin>
