<x-admin :breadcrumb="[
    ['label' => 'Enquiries', 'url' => route('admin.enquiries.index')],
    ['label' => 'Add enquiry']
]">
    <x-admin.form-page
        :action="route('admin.enquiries.store')"
        title="Add enquiry"
        description="Record a lead that came in by phone, in person, on WhatsApp or anywhere else."
        :back="route('admin.enquiries.index')"
        submit="Add enquiry"
        submitting="Adding…"
    >
        @include('admin.enquiries.partials.form', ['enquiry' => null])
    </x-admin.form-page>
</x-admin>
