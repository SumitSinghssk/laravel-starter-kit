@php
    use App\Enums\EnquiryStatus;
    use Illuminate\Support\Str;

    $canDelete = auth()
        ->user()
        ->can('admin.enquiries.delete');
    $canCreate = auth()
        ->user()
        ->can('admin.enquiries.create');

    $headers = ['Contact', 'Message', 'Status', 'Assigned', 'Received', 'Actions'];

    $bulk = \App\Http\Controllers\Admin\BulkActionController::allowed('enquiries', auth()->user());
    if ($bulk) {
        array_unshift($headers, ['select' => true]);
    }

    $openCount = collect(EnquiryStatus::open())->sum(fn ($status) => $counts[$status] ?? 0);
    $tabs = [
        ['label' => 'Open', 'count' => $openCount, 'query' => ['status' => 'open']],
        ...collect(EnquiryStatus::cases())
            ->map(fn ($status) => ['label' => $status->label(), 'count' => $counts[$status->value] ?? 0, 'query' => ['status' => $status->value], 'dot' => $status->dot()])
            ->all(),
        ['label' => 'Follow-up due', 'count' => $dueCount, 'query' => ['follow_up' => 'due'], 'alert' => $dueCount > 0],
        ['label' => 'Mine', 'count' => $mineCount, 'query' => ['assigned' => 'me', 'status' => 'open']],
        ['label' => 'All', 'count' => $counts->sum(), 'query' => []],
    ];
    $activeQuery = collect(request()->only(['status', 'follow_up', 'assigned']))
        ->filter()
        ->all();
    $filtersOnly = collect(request()->except(['status', 'follow_up', 'assigned', 'page']))
        ->filter()
        ->isNotEmpty();

    $followUpChip = [
        'overdue' => ['Overdue', 'bg-red-50 text-red-700 ring-red-600/15 dark:bg-red-500/10 dark:text-red-300 dark:ring-red-400/20'],
        'today' => ['Today', 'bg-amber-50 text-amber-700 ring-amber-600/20 dark:bg-amber-500/10 dark:text-amber-300 dark:ring-amber-400/20'],
        'upcoming' => [null, 'bg-slate-50 text-slate-600 ring-slate-500/15 dark:bg-slate-800 dark:text-slate-300 dark:ring-slate-600/40'],
    ];
@endphp

<x-admin :breadcrumb="[['label' => 'Enquiries', 'url' => route('admin.enquiries.index')]]">
    <x-admin.page-header
        title="Enquiries"
        description="Every lead and message, from the website or added by your team."
        icon="inbox"
        :count="$enquiries->total()"
    >
        <x-slot:actions>
            <x-admin.trash-link type="enquiries" />
            <x-admin.transfer-actions type="enquiries" />
            @if ($canCreate)
                <x-admin.button :href="route('admin.enquiries.create')" icon="plus">Add enquiry</x-admin.button>
            @endif
        </x-slot>
    </x-admin.page-header>

    <nav aria-label="Enquiry views" class="scrollbar-hide -mx-4 mb-4 flex gap-1.5 overflow-x-auto px-4 sm:mx-0 sm:flex-wrap sm:px-0">
        @foreach ($tabs as $tab)
            @php
                $active = $activeQuery == $tab['query'];
            @endphp

            <a
                href="{{ route('admin.enquiries.index', [...request()->except(['status', 'follow_up', 'assigned', 'page']), ...$tab['query']]) }}"
                @if ($active) aria-current="page" @endif
                @class([
                    'inline-flex shrink-0 items-center gap-1.5 rounded-full border px-3 py-1.5 text-xs font-medium whitespace-nowrap transition',
                    'border-slate-900 bg-slate-900 text-white dark:border-white dark:bg-white dark:text-slate-900' => $active,
                    'border-slate-200 bg-white text-slate-600 hover:border-slate-300 hover:text-slate-900 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-300 dark:hover:text-white' => ! $active,
                ])
            >
                @isset($tab['dot'])
                    <span class="{{ $tab['dot'] }} h-1.5 w-1.5 rounded-full"></span>
                @endisset

                {{ $tab['label'] }}
                <span
                    @class([
                        'tabular rounded-full px-1.5 text-[10px] leading-4 font-semibold',
                        'bg-red-500 text-white' => ! empty($tab['alert']) && ! $active,
                        'bg-white/20 text-current' => $active,
                        'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400' => empty($tab['alert']) && ! $active,
                    ])
                >
                    {{ $tab['count'] }}
                </span>
            </a>
        @endforeach
    </nav>

    <x-admin.bulk type="enquiries" :ids="$enquiries->pluck('id')">
        <x-admin.table
            :headers="$headers"
            :data="$enquiries"
            :emptyMessage="$activeQuery || $filtersOnly ? 'No enquiries match this view' : 'No enquiries yet'"
            emptyIcon="inbox"
        >
            <x-slot:toolbar>
                @include('admin.enquiries.partials.filters')
            </x-slot>

            @foreach ($enquiries as $enquiry)
                @php
                    $email = $enquiry->field('email');
                    $phone = $enquiry->field('phone');
                    $subject = $enquiry->field('subject');
                    $message = $enquiry->field('message');
                    $unseen = $enquiry->is_unseen;
                    $url = route('admin.enquiries.show', $enquiry);
                    $followUp = $enquiry->follow_up_state;
                @endphp

                <tr>
                    @if ($bulk)
                        <x-admin.bulk.checkbox :value="$enquiry->id" :label="$enquiry->display_name" />
                    @endif

                    <td class="max-w-xs">
                        <div class="flex items-center gap-3">
                            <span class="relative shrink-0">
                                <span
                                    @class([
                                        'flex h-9 w-9 items-center justify-center rounded-full text-xs font-semibold',
                                        'bg-blue-50 text-blue-700 dark:bg-blue-500/10 dark:text-blue-300' => $unseen,
                                        'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400' => ! $unseen,
                                    ])
                                >
                                    @if ($enquiry->initials)
                                        {{ $enquiry->initials }}
                                    @else
                                        <x-admin.icon name="user" class="h-4 w-4" />
                                    @endif
                                </span>
                                @if ($unseen)
                                    <span
                                        class="absolute -top-0.5 -right-0.5 h-2.5 w-2.5 rounded-full bg-blue-500 ring-2 ring-white dark:ring-slate-900"
                                        title="Unread"
                                    ></span>
                                @endif
                            </span>

                            <div class="min-w-0">
                                <a
                                    href="{{ $url }}"
                                    @class([
                                        'block max-w-full truncate hover:text-blue-600 dark:hover:text-blue-400',
                                        'font-semibold text-slate-900 dark:text-white' => $unseen,
                                        'font-medium text-slate-700 dark:text-slate-200' => ! $unseen,
                                    ])
                                >
                                    {{ $enquiry->display_name }}
                                </a>

                                <div class="mt-0.5 flex min-w-0 flex-wrap items-center gap-x-3 gap-y-0.5 text-xs text-slate-400">
                                    @if ($email && $email !== $enquiry->display_name)
                                        <span class="flex min-w-0 items-center gap-1">
                                            <x-admin.icon name="mail" class="h-3 w-3 shrink-0" />
                                            <span class="truncate">{{ $email }}</span>
                                        </span>
                                    @endif

                                    @if ($phone && $phone !== $enquiry->display_name)
                                        <span class="flex items-center gap-1 whitespace-nowrap">
                                            <x-admin.icon name="phone" class="h-3 w-3 shrink-0" />
                                            {{ $phone }}
                                        </span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </td>

                    <td class="max-w-xs">
                        <a href="{{ $url }}" class="block min-w-48">
                            @if ($subject)
                                <span class="block truncate text-sm font-medium text-slate-800 dark:text-slate-100">{{ $subject }}</span>
                            @endif

                            @if ($message)
                                <span
                                    @class([
                                        'line-clamp-2 text-sm',
                                        'text-slate-700 dark:text-slate-200' => $unseen && ! $subject,
                                        'text-slate-500 dark:text-slate-400' => ! $unseen || $subject,
                                    ])
                                >
                                    {{ Str::limit($message, 140) }}
                                </span>
                            @endif

                            <span class="mt-1 inline-flex items-center gap-1 text-[11px] text-slate-400">
                                <x-admin.icon :name="$enquiry->is_manual ? 'user-check' : 'globe'" class="h-3 w-3" />
                                {{ $enquiry->source_label }}
                            </span>
                        </a>
                    </td>

                    <td>
                        <div class="flex flex-col items-start gap-1">
                            <x-admin.status-badge :status="$enquiry->status" />
                            @if ($followUp)
                                <span
                                    class="{{ $followUpChip[$followUp][1] }} inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[11px] font-medium whitespace-nowrap ring-1 ring-inset"
                                    title="Follow up on {{ $enquiry->follow_up_at->format('d M Y') }}"
                                >
                                    <x-admin.icon name="calendar" class="h-3 w-3" />
                                    {{ $followUpChip[$followUp][0] ?? $enquiry->follow_up_at->format('d M') }}
                                </span>
                            @endif
                        </div>
                    </td>

                    <td class="whitespace-nowrap">
                        @if ($enquiry->assignee)
                            <span class="inline-flex items-center gap-2 text-sm text-slate-700 dark:text-slate-200">
                                <span
                                    class="flex h-6 w-6 items-center justify-center rounded-full bg-violet-50 text-[10px] font-semibold text-violet-700 dark:bg-violet-500/10 dark:text-violet-300"
                                >
                                    {{ mb_strtoupper(mb_substr($enquiry->assignee->name, 0, 1)) }}
                                </span>
                                {{ $enquiry->assignee->id === auth()->id() ? 'You' : $enquiry->assignee->name }}
                            </span>
                        @else
                            <span class="text-sm text-slate-400">—</span>
                        @endif
                    </td>

                    <td class="whitespace-nowrap">
                        <time datetime="{{ $enquiry->created_at->toIso8601String() }}" title="{{ $enquiry->created_at->format('d M Y, H:i') }}">
                            <span class="block text-slate-700 dark:text-slate-200">{{ $enquiry->created_at->format('d M Y') }}</span>
                            <span class="text-xs text-slate-400">{{ $enquiry->created_at->diffForHumans() }}</span>
                        </time>
                    </td>

                    <td>
                        <div class="flex items-center justify-end gap-0.5">
                            <x-admin.tooltip text="Open">
                                <a
                                    href="{{ $url }}"
                                    aria-label="Open {{ $enquiry->display_name }}"
                                    class="inline-flex h-7.5 w-7.5 items-center justify-center rounded-lg text-slate-400 transition-colors hover:bg-slate-100 hover:text-slate-900 focus:outline-none focus-visible:ring-3 focus-visible:ring-blue-500/30 dark:hover:bg-slate-800 dark:hover:text-white"
                                >
                                    <x-admin.icon name="eye" class="h-4 w-4" />
                                </a>
                            </x-admin.tooltip>

                            @if ($canDelete)
                                <x-admin.delete-button
                                    size="sm"
                                    message="It will be moved to the Trash, where it can be restored."
                                    :route="route('admin.enquiries.destroy', $enquiry)"
                                    :id="$enquiry->id"
                                />
                            @endif
                        </div>
                    </td>
                </tr>
            @endforeach
        </x-admin.table>
    </x-admin.bulk>
</x-admin>
