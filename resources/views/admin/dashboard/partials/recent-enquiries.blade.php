@can('admin.enquiries.view')
    <x-admin.card title="Recent enquiries" text="The latest messages from your contact form." icon="inbox" :padded="false" class="xl:col-span-2">
        <x-slot:actions>
            <x-admin.button variant="ghost" size="sm" :href="route('admin.enquiries.index')">
                View all
                <x-slot:rightIcon>
                    <x-admin.icon name="arrow-right" class="h-3.5 w-3.5" />
                </x-slot>
            </x-admin.button>
        </x-slot>

        <ul class="divide-y divide-slate-100 dark:divide-slate-800">
            @forelse ($recentEnquiries as $enquiry)
                @php
                    $name = $enquiry->data['name'] ?? 'Unknown';
                    $initials = collect(preg_split('/\s+/', trim($name)))
                        ->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))
                        ->take(2)
                        ->implode('');
                @endphp

                <li>
                    <a
                        href="{{ route('admin.enquiries.index', ['search' => $enquiry->data['email'] ?? $name]) }}"
                        class="flex items-center gap-3 px-4 py-3 transition hover:bg-slate-50 sm:px-5 dark:hover:bg-slate-800/40"
                    >
                        <span
                            class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-blue-50 text-xs font-semibold text-blue-700 ring-1 ring-blue-100 dark:bg-blue-500/10 dark:text-blue-300 dark:ring-blue-500/20"
                        >
                            {{ $initials }}
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="flex items-center gap-1.5">
                                <span
                                    @class(['truncate text-sm text-slate-900 dark:text-white', 'font-semibold' => $enquiry->is_unseen, 'font-medium' => ! $enquiry->is_unseen])
                                >
                                    {{ $name }}
                                </span>
                                @if ($enquiry->is_unseen)
                                    <span class="h-1.5 w-1.5 shrink-0 rounded-full bg-blue-500" title="Unread"></span>
                                @endif
                            </span>
                            <span class="mt-0.5 flex items-center gap-1 truncate text-xs text-slate-500 dark:text-slate-400">
                                <x-admin.icon name="mail" class="h-3 w-3" />
                                <span class="truncate">{{ $enquiry->data['email'] ?? '—' }}</span>
                                @if (! empty($enquiry->data['service']))
                                    <span class="text-slate-300 dark:text-slate-600">·</span>
                                    <span class="truncate">{{ $enquiry->data['service'] }}</span>
                                @endif
                            </span>
                        </span>
                        <x-admin.status-badge :status="$enquiry->status" class="hidden sm:inline-flex" />
                        <span class="w-16 shrink-0 text-right text-xs text-slate-400">
                            {{ $enquiry->created_at->diffForHumans(null, true) }}
                        </span>
                    </a>
                </li>
            @empty
                <li class="flex flex-col items-center px-4 py-12 text-center">
                    <span
                        class="mb-3 flex h-10 w-10 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-400 shadow-xs dark:border-slate-700 dark:bg-slate-800"
                    >
                        <x-admin.icon name="inbox" class="h-5 w-5" />
                    </span>
                    <p class="text-sm font-semibold text-slate-700 dark:text-slate-200">No enquiries yet</p>
                    <p class="mt-0.5 text-xs text-slate-500">Messages from the contact form will show up here.</p>
                </li>
            @endforelse
        </ul>
    </x-admin.card>
@endcan
