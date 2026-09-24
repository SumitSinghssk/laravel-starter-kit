<x-admin
    :breadcrumb="[
        ['label' => 'Enquiries', 'url' => route('admin.enquiries.index')],
        ['label' => $enquiry->reference, 'url' => route('admin.enquiries.show', $enquiry)],
        ['label' => 'Edit']
    ]"
>
    <x-admin.form-page
        :action="route('admin.enquiries.update', $enquiry)"
        method="PUT"
        title="Edit enquiry details"
        :description="$enquiry->reference . ' · Fix a typo or add a phone number. Changes are noted in the activity.'"
        :back="route('admin.enquiries.show', $enquiry)"
        submit="Save changes"
        submitting="Saving…"
    >
        @include('admin.enquiries.partials.form', ['assignees' => collect()])
    </x-admin.form-page>
</x-admin>
