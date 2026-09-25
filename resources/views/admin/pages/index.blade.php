@php
    $canEdit = auth()
        ->user()
        ->can('admin.pages.edit');
    $canDelete = auth()
        ->user()
        ->can('admin.pages.delete');
    $canToggle = auth()
        ->user()
        ->can('admin.pages.toogle-status');
    $canManage = $canEdit || $canDelete;

    $headers = ['Page', 'Status', 'Published', 'Author'];
    if ($canManage) {
        $headers[] = 'Actions';
    }

    $bulk = \App\Http\Controllers\Admin\BulkActionController::allowed('pages', auth()->user());
    if ($bulk) {
        array_unshift($headers, ['select' => true]);
    }
@endphp

<x-admin :breadcrumb="[
    ['label' => 'Pages', 'url' => route('admin.pages.index')]
]">
    <x-admin.page-header
        title="Pages"
        description="Standalone pages on your website, such as About or Privacy."
        icon="file-text"
        :count="$pages->total()"
    >
        <x-slot:actions>
            <x-admin.trash-link type="pages" />
            <x-admin.transfer-actions type="pages" />
            @can('admin.pages.create')
                <x-admin.button :href="route('admin.pages.create')" icon="plus">New page</x-admin.button>
            @endcan
        </x-slot>
    </x-admin.page-header>

    <x-admin.bulk type="pages" :ids="$pages->pluck('id')">
        <x-admin.table :headers="$headers" :data="$pages" emptyMessage="No pages found" emptyIcon="file-text">
            <x-slot:toolbar>
                @include('admin.pages.partials.filters')
            </x-slot>

            @foreach ($pages as $page)
                <tr>
                    @if ($bulk)
                        <x-admin.bulk.checkbox :value="$page->id" :label="$page->title" />
                    @endif

                    <td class="max-w-md">
                        <div class="flex items-center gap-3">
                            <x-admin.thumb
                                :src="$page->featured_image ? asset('storage/' . $page->featured_image) : null"
                                icon="file-text"
                                class="h-10 w-14"
                            />

                            <div class="min-w-0">
                                @if ($canEdit)
                                    <a
                                        href="{{ route('admin.pages.edit', $page) }}"
                                        class="line-clamp-1 font-medium text-slate-900 hover:text-blue-600 dark:text-white dark:hover:text-blue-400"
                                    >
                                        {{ $page->title }}
                                    </a>
                                @else
                                    <span class="line-clamp-1 font-medium text-slate-900 dark:text-white">{{ $page->title }}</span>
                                @endif
                                <span class="mt-0.5 flex items-center gap-1 text-xs text-slate-400">
                                    <x-admin.icon name="link" class="h-3 w-3 shrink-0" />
                                    <span class="truncate">/{{ $page->slug }}</span>
                                </span>
                            </div>
                        </div>
                    </td>

                    <td>
                        <x-admin.status-toggle :url="route('admin.pages.toggle-status', $page->id)" :status="$page->status" :can="$canToggle" />
                    </td>

                    <td class="whitespace-nowrap">
                        @if ($page->published_at)
                            <span class="block text-slate-700 dark:text-slate-200">{{ local_date($page->published_at) }}</span>
                            <span class="text-xs text-slate-400">
                                {{ $page->published_at->isFuture() ? 'Scheduled' : $page->published_at->diffForHumans() }}
                            </span>
                        @else
                            <span class="text-slate-400">—</span>
                        @endif
                    </td>

                    <td class="whitespace-nowrap">
                        @if ($page->user)
                            <span class="flex items-center gap-2">
                                <img src="{{ $page->user->avatar_url }}" alt="" class="h-6 w-6 rounded-full object-cover" />
                                <span class="text-slate-700 dark:text-slate-200">{{ $page->user->name }}</span>
                            </span>
                        @else
                            <span class="text-slate-400">—</span>
                        @endif
                    </td>

                    @if ($canManage)
                        <td>
                            <x-admin.row-actions
                                delete-message="It will be moved to the Trash, where it can be restored."
                                size="sm"
                                :editRoute="route('admin.pages.edit', $page)"
                                :canEdit="$canEdit"
                                :deleteRoute="route('admin.pages.destroy', $page)"
                                :deleteId="$page->id"
                                :canDelete="$canDelete"
                            />
                        </td>
                    @endif
                </tr>
            @endforeach
        </x-admin.table>
    </x-admin.bulk>
</x-admin>
