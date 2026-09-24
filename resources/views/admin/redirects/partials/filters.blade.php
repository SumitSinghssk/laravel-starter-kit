<x-admin.filter.bar :action="route('admin.redirects.index')" search="Search by old URL, new URL or note…">
    <x-admin.filter.select
        name="type"
        label="Type"
        icon="redirect"
        :options="collect(\App\Models\Redirect::STATUS_CODES)->map(fn ($code) => $code['label'])"
    />
    <x-admin.filter.select name="status" label="Status" icon="circle-dot" :options="\App\Enums\CommonStatusEnum::dotOptions()" />
</x-admin.filter.bar>
