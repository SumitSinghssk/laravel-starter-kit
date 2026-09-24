@php
    $canRestore = auth()
        ->user()
        ->can('admin.trash.restore');
    $canDelete = auth()
        ->user()
        ->can('admin.trash.delete');
    $canAct = $canRestore || $canDelete;
    $total = array_sum($counts);
    $days = config('trash.purge_after_days');
    $pageKeys = $items->pluck('key')->all();
    $tabUrl = fn (?string $key) => route('admin.trash.index', array_filter(['type' => $key, 'sort' => $sort === 'oldest' ? 'oldest' : null, 'search' => request('search')]));
@endphp

<x-admin :breadcrumb="[['label' => 'Trash', 'url' => route('admin.trash.index')]]">
    <div
        x-data="{
            selected: [],
            pageKeys: @js($pageKeys),
            confirming: null,
            get allSelected() {
                return this.pageKeys.length && this.pageKeys.every((key) => this.selected.includes(key))
            },
            toggleAll() {
                this.selected = this.allSelected ? [] : [...this.pageKeys]
            },
            ask(action, keys, title, message, label) {
                this.confirming = { action, keys, title, message, label }
            },
            submit() {
                const form = document.getElementById('trash-form-' + this.confirming.action)
                form.querySelectorAll('input[name=\'items[]\']').forEach((input) => input.remove())
                for (const key of this.confirming.keys) {
                    const input = document.createElement('input')
                    input.type = 'hidden'
                    input.name = 'items[]'
                    input.value = key
                    form.appendChild(input)
                }
                form.submit()
            },
        }"
        x-on:keydown.escape.window="confirming = null"
    >
        <x-admin.page-header
            title="Trash"
            :description="$days ? 'Deleted items stay here for ' . $days . ' days, then they are removed for good.' : 'Deleted items stay here until you remove them.'"
            icon="trash"
            :count="$total"
        >
            @if ($canDelete && $items->total())
                <x-slot:actions>
                    <x-admin.button
                        type="button"
                        variant="danger-outline"
                        icon="trash"
                        x-on:click="ask(
                            'empty',
                            [],
                            {{ \Illuminate\Support\Js::from($type ? 'Empty ' . strtolower($types[$type]['label']) . ' from the trash?' : 'Empty the trash?') }},
                            {{ \Illuminate\Support\Js::from('All ' . ($type ? strtolower($types[$type]['label']) : 'items') . ' in the trash will be deleted permanently, with their images. This cannot be undone.') }},
                            'Empty trash'
                        )"
                    >
                        Empty trash
                    </x-admin.button>
                </x-slot>
            @endif
        </x-admin.page-header>

        <x-admin.tabs
            label="Trash sections"
            :tabs="collect([['key' => null, 'label' => 'All items', 'icon' => 'trash', 'count' => $total]])
                ->merge(collect($types)->map(fn ($t, $k) => ['key' => $k, 'label' => $t['label'], 'icon' => $t['icon'], 'count' => $counts[$k] ?? 0])->values())
                ->filter(fn ($tab) => $tab['key'] === null || $tab['count'] || $tab['key'] === $type)
                ->map(fn ($tab) => [...$tab, 'url' => $tabUrl($tab['key']), 'active' => $tab['key'] === $type])
                ->values()
            ->all()"
        />

        <div class="rounded-xl border border-slate-200/80 bg-white shadow-xs dark:border-slate-800 dark:bg-slate-900">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 p-3 dark:border-slate-800">
                <x-admin.filter.bar
                    :action="route('admin.trash.index')"
                    :keep="['type' => $type]"
                    search="Search the trash…"
                    class="min-w-0 flex-1"
                >
                    <x-admin.filter.select
                        name="sort"
                        label="Sort"
                        icon="sliders"
                        :options="['newest' => 'Recently deleted', 'oldest' => 'Deleted longest ago']"
                    />
                </x-admin.filter.bar>

                @if ($canAct)
                    <div class="flex items-center gap-2" x-show="selected.length" x-cloak x-transition.opacity>
                        <span class="tabular text-sm text-slate-600 dark:text-slate-300" x-text="`${selected.length} selected`"></span>
                        @if ($canRestore)
                            <x-admin.button
                                type="button"
                                size="sm"
                                variant="secondary"
                                icon="refresh"
                                x-on:click="ask('restore', selected, `Restore ${selected.length} item${selected.length === 1 ? '' : 's'}?`, 'They go back to where they came from, with their images.', 'Restore')"
                            >
                                Restore
                            </x-admin.button>
                        @endif

                        @if ($canDelete)
                            <x-admin.button
                                type="button"
                                size="sm"
                                variant="danger"
                                icon="trash"
                                x-on:click="ask('destroy', selected, `Delete ${selected.length} item${selected.length === 1 ? '' : 's'} permanently?`, 'They and their images will be removed for good. This cannot be undone.', 'Delete forever')"
                            >
                                Delete forever
                            </x-admin.button>
                        @endif
                    </div>
                @endif
            </div>

            @if ($items->isEmpty())
                <div class="flex flex-col items-center justify-center px-6 py-16 text-center">
                    <span
                        class="mb-3 flex h-11 w-11 items-center justify-center rounded-xl border border-slate-200 bg-slate-50 text-slate-400 dark:border-slate-700 dark:bg-slate-800"
                    >
                        <x-admin.icon :name="request('search') ? 'search' : 'trash'" class="h-5 w-5" />
                    </span>
                    <h3 class="text-sm font-semibold text-slate-900 dark:text-white">
                        {{ request('search') ? 'Nothing matches your search' : 'The trash is empty' }}
                    </h3>
                    <p class="mt-1 max-w-sm text-sm text-slate-500 dark:text-slate-400">
                        {{ request('search') ? 'Try another search.' : 'Items you delete from blog posts, pages, categories, testimonials, users and enquiries appear here.' }}
                    </p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="admin-table w-full text-left">
                        <thead class="border-b border-slate-100 text-xs font-medium text-slate-500 dark:border-slate-800 dark:text-slate-400">
                            <tr>
                                @if ($canAct)
                                    <th class="w-10 px-4 py-2.5">
                                        <input
                                            type="checkbox"
                                            x-bind:checked="allSelected"
                                            x-on:change="toggleAll()"
                                            class="h-4 w-4 cursor-pointer rounded border-slate-300 text-blue-600 focus:ring-blue-500/30 dark:border-slate-600 dark:bg-slate-800"
                                            aria-label="Select all on this page"
                                        />
                                    </th>
                                @endif

                                <th class="px-4 py-2.5">Item</th>
                                <th class="px-4 py-2.5">Type</th>
                                <th class="px-4 py-2.5">Deleted</th>
                                @if ($canAct)
                                    <th class="px-4 py-2.5 text-right">Actions</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            @foreach ($items as $item)
                                <tr x-bind:class="selected.includes(@js($item['key'])) && 'bg-blue-50/50 dark:bg-blue-500/5'">
                                    @if ($canAct)
                                        <td class="w-10">
                                            <input
                                                type="checkbox"
                                                value="{{ $item['key'] }}"
                                                x-model="selected"
                                                class="h-4 w-4 cursor-pointer rounded border-slate-300 text-blue-600 focus:ring-blue-500/30 dark:border-slate-600 dark:bg-slate-800"
                                                aria-label="Select {{ $item['title'] }}"
                                            />
                                        </td>
                                    @endif

                                    <td class="max-w-md">
                                        <div class="flex items-center gap-3">
                                            <span
                                                class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400"
                                            >
                                                <x-admin.icon :name="$item['icon']" class="h-4 w-4" />
                                            </span>
                                            <span class="min-w-0">
                                                <span class="block truncate font-medium text-slate-900 dark:text-white">{{ $item['title'] }}</span>
                                                @if ($item['subtitle'])
                                                    <span class="block truncate text-xs text-slate-500 dark:text-slate-400">
                                                        {{ $item['subtitle'] }}
                                                    </span>
                                                @endif
                                            </span>
                                        </div>
                                    </td>
                                    <td class="whitespace-nowrap">
                                        <span
                                            class="rounded-md bg-slate-100 px-1.5 py-0.5 text-xs font-medium text-slate-600 dark:bg-slate-800 dark:text-slate-300"
                                        >
                                            {{ $item['type_label'] }}
                                        </span>
                                    </td>
                                    <td class="whitespace-nowrap">
                                        <span
                                            class="block text-slate-700 dark:text-slate-200"
                                            title="{{ $item['deleted_at']->format('d M Y, H:i') }}"
                                        >
                                            {{ $item['deleted_at']->diffForHumans() }}
                                        </span>
                                        @if ($item['purge_at'])
                                            @php($daysLeft = max(0, (int) ceil(now()->diffInDays($item['purge_at'], false))))
                                            <span
                                                @class(['text-xs', 'text-amber-600 dark:text-amber-400' => $daysLeft <= 3, 'text-slate-400' => $daysLeft > 3])
                                            >
                                                {{ $daysLeft === 0 ? 'Deleted for good today' : 'Deleted for good in ' . $daysLeft . ' ' . Str::plural('day', $daysLeft) }}
                                            </span>
                                        @endif
                                    </td>
                                    @if ($canAct)
                                        <td class="whitespace-nowrap">
                                            <div class="flex items-center justify-end gap-1.5">
                                                @if ($canRestore)
                                                    <x-admin.button
                                                        type="button"
                                                        size="sm"
                                                        variant="secondary"
                                                        icon="refresh"
                                                        x-on:click="ask('restore', [{{ \Illuminate\Support\Js::from($item['key']) }}], {{ \Illuminate\Support\Js::from('Restore “' . $item['title'] . '”?') }}, 'It goes back to where it came from.', 'Restore')"
                                                    >
                                                        Restore
                                                    </x-admin.button>
                                                @endif

                                                @if ($canDelete)
                                                    <x-admin.button
                                                        type="button"
                                                        size="sm"
                                                        variant="ghost"
                                                        icon-only
                                                        icon="trash"
                                                        class="text-red-500 hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-500/10"
                                                        x-on:click="ask('destroy', [{{ \Illuminate\Support\Js::from($item['key']) }}], {{ \Illuminate\Support\Js::from('Delete “' . $item['title'] . '” permanently?') }}, 'It and its images will be removed for good. This cannot be undone.', 'Delete forever')"
                                                        aria-label="Delete permanently"
                                                        title="Delete permanently"
                                                    />
                                                @endif
                                            </div>
                                        </td>
                                    @endif
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if ($items->hasPages())
                    <div class="border-t border-slate-100 px-4 py-3 dark:border-slate-800">
                        {{ $items->links('vendor.pagination.tailwind') }}
                    </div>
                @endif
            @endif
        </div>

        <form id="trash-form-restore" method="POST" action="{{ route('admin.trash.restore') }}" class="hidden">@csrf</form>
        <form id="trash-form-destroy" method="POST" action="{{ route('admin.trash.destroy') }}" class="hidden">
            @csrf
            @method('DELETE')
        </form>
        <form id="trash-form-empty" method="POST" action="{{ route('admin.trash.empty') }}" class="hidden">
            @csrf
            @method('DELETE')
            @if ($type)
                <input type="hidden" name="type" value="{{ $type }}" />
            @endif
        </form>

        <template x-teleport="#admin-portal">
            <div
                x-show="confirming"
                x-cloak
                x-transition.opacity
                class="fixed inset-0 z-110 flex items-center justify-center bg-slate-950/60 p-4 backdrop-blur-sm"
                role="alertdialog"
                aria-modal="true"
            >
                <div class="w-full max-w-sm rounded-2xl bg-white p-5 shadow-2xl dark:bg-slate-900" x-on:click.outside="confirming = null">
                    <div class="flex gap-3">
                        <span
                            class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full"
                            x-bind:class="
                                confirming?.action === 'restore'
                                    ? 'bg-blue-50 text-blue-600 dark:bg-blue-500/10 dark:text-blue-400'
                                    : 'bg-red-50 text-red-600 dark:bg-red-500/10 dark:text-red-400'
                            "
                        >
                            <x-admin.icon name="refresh" class="h-5 w-5" x-show="confirming?.action === 'restore'" />
                            <x-admin.icon name="trash" class="h-5 w-5" x-show="confirming?.action !== 'restore'" />
                        </span>
                        <div>
                            <h3 class="text-sm font-semibold text-slate-900 dark:text-white" x-text="confirming?.title"></h3>
                            <p class="mt-1 text-sm text-slate-600 dark:text-slate-300" x-text="confirming?.message"></p>
                        </div>
                    </div>
                    <div class="mt-5 flex justify-end gap-2">
                        <x-admin.button type="button" size="sm" variant="secondary" x-on:click="confirming = null">Cancel</x-admin.button>
                        <x-admin.button
                            type="button"
                            size="sm"
                            x-bind:class="confirming?.action !== 'restore' && '!bg-red-600 hover:!bg-red-700'"
                            x-on:click="submit()"
                        >
                            <span x-text="confirming?.label">OK</span>
                        </x-admin.button>
                    </div>
                </div>
            </div>
        </template>
    </div>
</x-admin>
