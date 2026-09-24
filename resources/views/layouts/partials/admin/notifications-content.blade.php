<div class="flex flex-col">
    <div class="hidden items-center justify-between border-b border-slate-100 px-4 py-3 md:flex dark:border-slate-800">
        <div class="flex items-center gap-2">
            <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Notifications</h3>
            <span
                x-show="$store.notif.unread > 0"
                x-text="$store.notif.unread + ' new'"
                class="rounded-full bg-blue-50 px-1.5 py-px text-[11px] font-semibold text-blue-700 dark:bg-blue-500/10 dark:text-blue-300"
            ></span>
        </div>

        @can('admin.notifications.mark-all-as-read')
            <button
                x-show="$store.notif.unread > 0"
                x-on:click="$store.notif.markAllRead()"
                class="flex cursor-pointer items-center gap-1 text-xs font-medium text-slate-500 transition hover:text-blue-600 dark:text-slate-400 dark:hover:text-blue-400"
            >
                <x-admin.icon name="check" class="h-3.5 w-3.5" />
                Mark all as read
            </button>
        @endcan
    </div>

    <div class="custom-scrollbar max-h-96 flex-1 divide-y divide-slate-100 overflow-y-auto dark:divide-slate-800">
        <template x-if="$store.notif.items.length === 0">
            <div class="flex flex-col items-center px-6 py-10 text-center">
                <span class="mb-3 flex h-10 w-10 items-center justify-center rounded-full bg-slate-100 text-slate-400 dark:bg-slate-800">
                    <x-admin.icon name="bell" class="h-5 w-5" />
                </span>
                <p class="text-sm font-medium text-slate-700 dark:text-slate-200">You're all caught up</p>
                <p class="mt-0.5 text-xs text-slate-500">New enquiries and sign-ins will show here.</p>
            </div>
        </template>

        <template x-for="notif in $store.notif.items" :key="notif.id">
            <a
                :href="`{{ url('admin/notifications') }}/${notif.id}/view`"
                x-on:click="$store.notif.markRead(notif.id)"
                class="flex items-start gap-3 px-4 py-3 transition hover:bg-slate-50 dark:hover:bg-slate-800/50"
                x-bind:class="! notif.seen && 'bg-blue-50/40 dark:bg-blue-500/[0.04]'"
            >
                <span
                    class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-lg"
                    x-bind:class="
                        notif.type === 'Enquiry'
                            ? 'bg-blue-50 text-blue-600 dark:bg-blue-500/10 dark:text-blue-400'
                            : 'bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-400'
                    "
                >
                    <x-admin.icon name="inbox" x-show="notif.type === 'Enquiry'" class="h-4 w-4" />
                    <x-admin.icon name="log-in" x-show="notif.type !== 'Enquiry'" class="h-4 w-4" />
                </span>

                <span class="min-w-0 flex-1">
                    <span class="flex items-center gap-2">
                        <span class="truncate text-sm font-medium text-slate-900 dark:text-white" x-text="notif.title"></span>
                        <span x-show="! notif.seen" class="h-1.5 w-1.5 shrink-0 rounded-full bg-blue-500" aria-label="Unread"></span>
                    </span>
                    <span class="mt-0.5 line-clamp-2 block text-xs text-slate-500 dark:text-slate-400" x-text="notif.message"></span>
                    <span class="mt-1 block text-[11px] text-slate-400" x-text="notif.time"></span>
                </span>
            </a>
        </template>
    </div>

    <div class="hidden border-t border-slate-100 p-2 md:block dark:border-slate-800">
        <a
            href="{{ route('admin.notifications.list') }}"
            class="flex w-full items-center justify-center gap-1.5 rounded-lg px-4 py-2 text-xs font-semibold text-slate-600 transition hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800"
        >
            View all notifications
            <x-admin.icon name="arrow-right" class="h-3.5 w-3.5" />
        </a>
    </div>
</div>
