<x-admin.filter.bar :action="route('admin.seo.index')" search="Search by page, slug or meta title…">
    <x-admin.filter.select
        name="index"
        label="Indexing"
        icon="eye"
        :options="[
            '1' => ['label' => 'Indexed', 'dot' => 'bg-emerald-500'],
            '0' => ['label' => 'No index', 'dot' => 'bg-slate-400'],
        ]"
    />
</x-admin.filter.bar>
