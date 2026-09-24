@php
    $canDelete = auth()
        ->user()
        ->can('admin.enquiries.delete');
    $canView = auth()
        ->user()
        ->can('admin.enquiries.view');

    $headers = ['Contact', 'Message', 'Source', 'Status', 'Received'];
    if ($canView || $canDelete) {
        $headers[] = 'Actions';
    }

    $bulk = \App\Http\Controllers\Admin\BulkActionController::allowed('enquiries', auth()->user());
    if ($bulk) {
        array_unshift($headers, ['select' => true]);
    }
@endphp

<x-admin :breadcrumb="[['label' => 'Enquiries', 'url' => route('admin.enquiries.index')]]">
    <x-admin.page-header
        title="Enquiries"
        description="Messages and leads coming in from your website forms."
        icon="inbox"
        :count="$enquiries->total()"
    >
        <x-slot:actions>
            <x-admin.trash-link type="enquiries" />
            <x-admin.transfer-actions type="enquiries" />
        </x-slot>
    </x-admin.page-header>

    <x-admin.bulk type="enquiries" :ids="$enquiries->pluck('id')">
        <x-admin.table :headers="$headers" :data="$enquiries" emptyMessage="No enquiries found" emptyIcon="inbox">
            <x-slot:toolbar>
                @include('admin.enquiries.partials.filters')
            </x-slot>

            @foreach ($enquiries as $enquiry)
                @php
                    $data = $enquiry->data ?? [];
                    $name = is_scalar($data['name'] ?? null) ? (string) $data['name'] : null;
                    $email = is_scalar($data['email'] ?? null) ? (string) $data['email'] : null;
                    $phone = is_scalar($data['phone'] ?? null) ? (string) $data['phone'] : null;
                    $message = is_scalar($data['message'] ?? null) ? (string) $data['message'] : null;

                    $primary = $name ?: ($email ?: ($phone ?: 'Anonymous'));
                    $initials = $name
                        ? collect(preg_split('/\s+/', trim($name)))
                            ->filter()
                            ->take(2)
                            ->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))
                            ->implode('')
                        : null;
                    $unseen = $enquiry->is_unseen;

                    $openAttrs = 'x-on:click="$dispatch(\'open-modal\', \'enquiry-' . $enquiry->id . '\')"';
                @endphp

                <tr>
                    @if ($bulk)
                        <x-admin.bulk.checkbox :value="$enquiry->id" :label="$name ?: ($email ?: 'enquiry')" />
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
                                    @if ($initials)
                                        {{ $initials }}
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
                                @if ($canView)
                                    <button
                                        type="button"
                                        {!! $openAttrs !!}
                                        @class([
                                            'block max-w-full cursor-pointer truncate text-left hover:text-blue-600 dark:hover:text-blue-400',
                                            'font-semibold text-slate-900 dark:text-white' => $unseen,
                                            'font-medium text-slate-700 dark:text-slate-200' => ! $unseen,
                                        ])
                                    >
                                        {{ $primary }}
                                    </button>
                                @else
                                    <span
                                        @class(['block truncate', 'font-semibold text-slate-900 dark:text-white' => $unseen, 'font-medium text-slate-700 dark:text-slate-200' => ! $unseen])
                                    >
                                        {{ $primary }}
                                    </span>
                                @endif

                                <div class="mt-0.5 flex min-w-0 flex-wrap items-center gap-x-3 gap-y-0.5 text-xs text-slate-400">
                                    @if ($email && $email !== $primary)
                                        <span class="flex min-w-0 items-center gap-1">
                                            <x-admin.icon name="mail" class="h-3 w-3 shrink-0" />
                                            <span class="truncate">{{ $email }}</span>
                                        </span>
                                    @endif

                                    @if ($phone && $phone !== $primary)
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
                        @if ($message)
                            <p
                                @class(['line-clamp-2 min-w-48 text-sm', 'text-slate-700 dark:text-slate-200' => $unseen, 'text-slate-500 dark:text-slate-400' => ! $unseen])
                            >
                                {{ \Illuminate\Support\Str::limit($message, 140) }}
                            </p>
                        @else
                            <span class="text-slate-400">—</span>
                        @endif
                    </td>

                    <td>
                        <span
                            class="inline-flex items-center gap-1 rounded-md border border-slate-200 bg-white px-1.5 py-0.5 text-xs font-medium whitespace-nowrap text-slate-600 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300"
                        >
                            <x-admin.icon name="globe" class="h-3 w-3 text-slate-400" />
                            {{ \Illuminate\Support\Str::headline((string) $enquiry->source) }}
                        </span>
                    </td>

                    <td>
                        <x-admin.status-badge :status="$enquiry->status" />
                    </td>

                    <td class="whitespace-nowrap">
                        <time datetime="{{ $enquiry->created_at->toIso8601String() }}" title="{{ $enquiry->created_at->format('d M Y, H:i') }}">
                            <span class="block text-slate-700 dark:text-slate-200">{{ $enquiry->created_at->format('d M Y') }}</span>
                            <span class="text-xs text-slate-400">{{ $enquiry->created_at->diffForHumans() }}</span>
                        </time>
                    </td>

                    @if ($canView || $canDelete)
                        <td>
                            <div class="flex items-center justify-end gap-0.5">
                                @if ($canView)
                                    <x-admin.tooltip text="View details">
                                        <button
                                            type="button"
                                            {!! $openAttrs !!}
                                            aria-label="View details"
                                            class="inline-flex h-7.5 w-7.5 cursor-pointer items-center justify-center rounded-lg text-slate-400 transition-colors hover:bg-slate-100 hover:text-slate-900 focus:outline-none focus-visible:ring-3 focus-visible:ring-blue-500/30 dark:hover:bg-slate-800 dark:hover:text-white"
                                        >
                                            <x-admin.icon name="eye" class="h-4 w-4" />
                                        </button>
                                    </x-admin.tooltip>
                                @endif

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
                    @endif
                </tr>
            @endforeach
        </x-admin.table>
    </x-admin.bulk>

    @foreach ($enquiries as $enquiry)
        @include('admin.enquiries.partials.details-modal', ['enquiry' => $enquiry])
    @endforeach
</x-admin>
