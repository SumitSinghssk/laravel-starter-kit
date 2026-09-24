@php
    $statusOptions = [
        'new' => ['label' => 'New', 'dot' => 'bg-blue-500'],
        'seen' => ['label' => 'Seen', 'dot' => 'bg-slate-400'],
        'pending' => ['label' => 'Pending', 'dot' => 'bg-amber-500'],
        'closed' => ['label' => 'Closed', 'dot' => 'bg-emerald-500'],
    ];

    $sourceOptions = collect($sources)
        ->filter()
        ->mapWithKeys(fn ($source) => [$source => \Illuminate\Support\Str::headline($source)])
        ->all();

    $readOptions = [
        'unseen' => ['label' => 'Unread', 'dot' => 'bg-blue-500'],
        'seen' => ['label' => 'Read', 'dot' => 'bg-slate-400'],
    ];
@endphp

<x-admin.filter.bar :action="route('admin.enquiries.index')" search="Search by name, email, phone, company…">
    <x-admin.filter.select name="status" label="Status" icon="circle-dot" :options="$statusOptions" />

    <x-admin.filter.select name="source" label="Source" icon="globe" :options="$sourceOptions" />

    <x-admin.filter.select name="seen" label="Read status" icon="mail-open" :options="$readOptions" />

    <x-admin.filter.date-range label="Received" start-name="date_from" end-name="date_to" />
</x-admin.filter.bar>
