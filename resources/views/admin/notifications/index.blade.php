@php
    $headers = ['Notification', 'Status', 'Received', 'Actions'];
    $hasUnread = $notifications->whereNull('seen_at')->count() > 0;

    $typeIcons = [
        'Enquiry' => 'inbox',
        'Admin Login' => 'log-in',
    ];
@endphp

<x-admin :breadcrumb="[
    ['label' => 'Notifications', 'url' => route('admin.notifications.list')]
]">
    <x-admin.page-header
        title="Notifications"
        description="Stay on top of new enquiries, sign-ins and other alerts."
        icon="bell"
        :count="$notifications->total()"
    >
        @if ($hasUnread)
            <x-slot:actions>
                <div x-data x-show="$store.notif.unread > 0" x-cloak x-transition>
                    <x-admin.button
                        variant="secondary"
                        type="button"
                        icon="check-circle"
                        x-on:click="$store.notif.markAllRead(); $dispatch('mark-all-read')"
                    >
                        Mark all as read
                    </x-admin.button>
                </div>
            </x-slot>
        @endif
    </x-admin.page-header>

    <x-admin.table :headers="$headers" :data="$notifications" emptyMessage="No notifications yet" emptyIcon="bell">
        @foreach ($notifications as $notification)
            @php
                $typeIcon = $typeIcons[$notification->type] ?? 'bell';
            @endphp

            <tr
                x-data="{
                    isSeen: {{ $notification->isSeen() ? 'true' : 'false' }},
                    data: @js($notification->data),
                }"
                x-on:mark-all-read.window="isSeen = true"
                x-bind:class="! isSeen ? 'bg-blue-50/40 dark:bg-blue-500/5' : ''"
            >
                <td class="max-w-2xl min-w-64">
                    <div class="flex items-start gap-3">
                        <span class="relative mt-0.5 shrink-0">
                            <span
                                class="flex h-9 w-9 items-center justify-center rounded-lg border transition-colors"
                                x-bind:class="
                                    isSeen
                                        ? 'border-slate-200 bg-slate-50 text-slate-400 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-500'
                                        : 'border-blue-100 bg-blue-50 text-blue-600 dark:border-blue-500/20 dark:bg-blue-500/10 dark:text-blue-300'
                                "
                            >
                                <x-admin.icon :name="$typeIcon" class="h-4 w-4" />
                            </span>
                            <span
                                x-show="!isSeen"
                                class="absolute -top-0.5 -right-0.5 h-2.5 w-2.5 rounded-full bg-blue-500 ring-2 ring-white dark:ring-slate-900"
                            ></span>
                        </span>

                        <div class="min-w-0">
                            <span
                                class="block transition-colors"
                                x-bind:class="
                                    isSeen
                                        ? 'font-medium text-slate-600 dark:text-slate-300'
                                        : 'font-semibold text-slate-900 dark:text-white'
                                "
                            >
                                {{ $notification->title }}
                            </span>

                            @if ($notification->message)
                                <span
                                    class="mt-0.5 block text-sm transition-colors"
                                    x-bind:class="
                                        isSeen
                                            ? 'text-slate-400 dark:text-slate-500'
                                            : 'text-slate-600 dark:text-slate-300'
                                    "
                                >
                                    {{ $notification->message }}
                                </span>
                            @endif

                            <div class="mt-1.5 flex flex-wrap items-center gap-1.5">
                                @if ($notification->type)
                                    <span
                                        class="inline-flex items-center gap-1 rounded-md border border-slate-200 bg-white px-1.5 py-0.5 text-xs font-medium whitespace-nowrap text-slate-600 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300"
                                    >
                                        <x-admin.icon :name="$typeIcon" class="h-3 w-3 text-slate-400" />
                                        {{ $notification->type }}
                                    </span>
                                @endif
                            </div>

                            @if ($notification->data)
                                <dl class="mt-1.5 max-w-2xl space-y-0.5 text-xs text-slate-400 dark:text-slate-500">
                                    @foreach ($notification->data as $key => $value)
                                        @php
                                            $dataLabel = strtolower((string) $key) === 'ip' ? 'IP' : ucfirst(str_replace('_', ' ', $key));
                                            $dataValue = is_array($value) ? json_encode($value) : $value;
                                        @endphp

                                        <div class="flex min-w-0 gap-1" title="{{ $dataValue }}">
                                            <dt class="shrink-0 font-medium text-slate-500 dark:text-slate-400">{{ $dataLabel }}:</dt>
                                            <dd class="min-w-0 truncate">{{ $dataValue }}</dd>
                                        </div>
                                    @endforeach
                                </dl>
                            @endif
                        </div>
                    </div>
                </td>

                <td>
                    <template x-if="!isSeen">
                        <x-admin.status-badge tone="info" label="New" />
                    </template>

                    <template x-if="isSeen">
                        <x-admin.status-badge tone="neutral" label="Read" />
                    </template>
                </td>

                <td class="whitespace-nowrap">
                    <span class="block text-slate-700 dark:text-slate-200">{{ local_date($notification->created_at) }}</span>
                    <span class="text-xs text-slate-400">{{ $notification->created_at->diffForHumans() }}</span>
                </td>

                <td>
                    <div class="flex items-center justify-end gap-0.5">
                        <template x-if="!isSeen">
                            <x-admin.tooltip text="Mark as read">
                                <button
                                    type="button"
                                    x-on:click="
                                        $store.notif.markRead({{ $notification->id }})
                                        isSeen = true
                                    "
                                    aria-label="Mark as read"
                                    class="inline-flex h-7.5 w-7.5 cursor-pointer items-center justify-center rounded-lg text-slate-400 transition-colors hover:bg-blue-50 hover:text-blue-600 focus:outline-none focus-visible:ring-3 focus-visible:ring-blue-500/30 dark:hover:bg-blue-500/10 dark:hover:text-blue-400"
                                >
                                    <x-admin.icon name="check" class="h-4 w-4" />
                                </button>
                            </x-admin.tooltip>
                        </template>

                        @if ($notification->url)
                            <x-admin.tooltip text="View">
                                <a
                                    href="{{ $notification->url }}"
                                    aria-label="View"
                                    class="inline-flex h-7.5 w-7.5 items-center justify-center rounded-lg text-slate-400 transition-colors hover:bg-slate-100 hover:text-slate-900 focus:outline-none focus-visible:ring-3 focus-visible:ring-blue-500/30 dark:hover:bg-slate-800 dark:hover:text-white"
                                >
                                    <x-admin.icon name="arrow-up-right" class="h-4 w-4" />
                                </a>
                            </x-admin.tooltip>
                        @endif
                    </div>
                </td>
            </tr>
        @endforeach
    </x-admin.table>
</x-admin>
