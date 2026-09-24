@php
    $statusOptions = [
        'open' => ['label' => 'All open', 'dot' => 'bg-slate-900 dark:bg-white'],
        ...collect(\App\Enums\EnquiryStatus::options())
            ->map(fn ($option) => ['label' => $option['label'], 'dot' => $option['dot']])
            ->all(),
    ];

    $sourceOptions = collect($sources)
        ->filter()
        ->mapWithKeys(fn ($source) => [$source => \App\Models\Enquiry::MANUAL_SOURCES[$source] ?? \Illuminate\Support\Str::headline($source)])
        ->all();

    $assignedOptions =
        ['me' => 'Assigned to me', 'none' => 'Not assigned'] +
        $assignees
            ->reject(fn ($user) => $user->id === auth()->id())
            ->mapWithKeys(fn ($user) => [$user->id => $user->name])
            ->all();

    $followUpOptions = [
        'due' => ['label' => 'Due or overdue', 'dot' => 'bg-red-500'],
        'upcoming' => ['label' => 'Upcoming', 'dot' => 'bg-blue-500'],
        'none' => ['label' => 'No follow-up', 'dot' => 'bg-slate-300'],
    ];

    $readOptions = [
        'unseen' => ['label' => 'Unread', 'dot' => 'bg-blue-500'],
        'seen' => ['label' => 'Read', 'dot' => 'bg-slate-400'],
    ];
@endphp

<x-admin.filter.bar :action="route('admin.enquiries.index')" search="Search by name, email, phone, company, message…">
    <x-admin.filter.select name="status" label="Status" icon="circle-dot" :options="$statusOptions" />

    <x-admin.filter.select name="assigned" label="Assigned" icon="user-check" :options="$assignedOptions" />

    <x-admin.filter.select name="follow_up" label="Follow-up" icon="calendar" :options="$followUpOptions" />

    <x-admin.filter.select name="source" label="Source" icon="globe" :options="$sourceOptions" />

    <x-admin.filter.select name="seen" label="Read status" icon="mail-open" :options="$readOptions" />

    <x-admin.filter.date-range label="Received" start-name="date_from" end-name="date_to" />
</x-admin.filter.bar>
