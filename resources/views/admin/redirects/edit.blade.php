<x-admin :breadcrumb="[
    ['label' => 'Redirects', 'url' => route('admin.redirects.index')],
    ['label' => 'Edit Redirect']
]">
    <x-admin.form-page
        :action="route('admin.redirects.update', $redirect)"
        method="PUT"
        title="Edit redirect"
        :description="$redirect->source_path"
        :back="route('admin.redirects.index')"
        submit="Save changes"
        submitting="Saving…"
    >
        <x-slot:actions>
            @if ($redirect->status === \App\Enums\CommonStatusEnum::ACTIVE)
                <x-admin.button variant="secondary" :href="url($redirect->source_path)" target="_blank" rel="noopener" icon="external-link">
                    Test
                </x-admin.button>
            @endif

            @can('admin.redirects.delete')
                <x-admin.delete-button :route="route('admin.redirects.destroy', $redirect)" title="Delete this redirect?" />
            @endcan
        </x-slot>

        @include('admin.redirects.partials.form')
    </x-admin.form-page>
</x-admin>
