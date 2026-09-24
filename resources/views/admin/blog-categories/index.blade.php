@php
    $canEdit = auth()
        ->user()
        ->can('admin.blog-categories.edit');
    $canDelete = auth()
        ->user()
        ->can('admin.blog-categories.delete');
    $canToggle = auth()
        ->user()
        ->can('admin.blog-categories.toogle-status');
    $canManage = $canEdit || $canDelete;

    $headers = ['Category', 'Status', 'Description'];
    if ($canManage) {
        $headers[] = 'Actions';
    }

    $bulk = \App\Http\Controllers\Admin\BulkActionController::allowed('blog-categories', auth()->user());
    if ($bulk) {
        array_unshift($headers, ['select' => true]);
    }
@endphp

<x-admin :breadcrumb="[
    ['label' => 'Blog Categories', 'url' => route('admin.blog-categories.index')]
]">
    <x-admin.page-header
        title="Blog categories"
        description="Group your blog posts into categories and sub-categories."
        icon="tag"
        :count="$categories->total()"
    >
        <x-slot:actions>
            <x-admin.trash-link type="blog-categories" />
            <x-admin.transfer-actions type="blog-categories" />
            @can('admin.blog-categories.create')
                <x-admin.button :href="route('admin.blog-categories.create')" icon="plus">New category</x-admin.button>
            @endcan
        </x-slot>
    </x-admin.page-header>

    <x-admin.bulk type="blog-categories" :ids="$categories->pluck('id')">
        <x-admin.table :headers="$headers" :data="$categories" emptyMessage="No blog categories found" emptyIcon="tag">
            <x-slot:toolbar>
                @include('admin.blog-categories.partials.filters')
            </x-slot>

            @foreach ($categories as $category)
                <tr>
                    @if ($bulk)
                        <x-admin.bulk.checkbox :value="$category->id" :label="$category->name" />
                    @endif

                    <td>
                        <div class="flex items-center gap-3">
                            <x-admin.thumb :src="$category->image ? asset('storage/' . $category->image) : null" icon="tag" class="h-10 w-10" />

                            <div class="min-w-0">
                                @if ($canEdit)
                                    <a
                                        href="{{ route('admin.blog-categories.edit', $category) }}"
                                        class="line-clamp-1 font-medium text-slate-900 hover:text-blue-600 dark:text-white dark:hover:text-blue-400"
                                    >
                                        {{ $category->name }}
                                    </a>
                                @else
                                    <span class="line-clamp-1 font-medium text-slate-900 dark:text-white">{{ $category->name }}</span>
                                @endif
                                <span class="mt-0.5 flex flex-wrap items-center gap-x-2.5 gap-y-0.5 text-xs text-slate-400">
                                    <span class="flex min-w-0 items-center gap-1">
                                        <x-admin.icon name="link" class="h-3 w-3 shrink-0" />
                                        <span class="truncate">{{ $category->slug }}</span>
                                    </span>
                                    @if ($category->parent)
                                        <span class="flex min-w-0 items-center gap-1">
                                            <x-admin.icon name="folder-tree" class="h-3 w-3 shrink-0" />
                                            <span class="truncate">in {{ $category->parent->name }}</span>
                                        </span>
                                    @endif
                                </span>
                            </div>
                        </div>
                    </td>

                    <td>
                        <x-admin.status-toggle
                            :url="route('admin.blog-categories.toggle-status', $category->id)"
                            :status="$category->status"
                            :can="$canToggle"
                        />
                    </td>

                    <td>
                        @if ($category->description)
                            <p
                                class="line-clamp-2 max-w-xs min-w-48 text-xs text-slate-500 dark:text-slate-400"
                                title="{{ $category->description }}"
                            >
                                {{ str()->limit($category->description, 80) }}
                            </p>
                        @else
                            <span class="text-slate-400">—</span>
                        @endif
                    </td>

                    @if ($canManage)
                        <td>
                            <x-admin.row-actions
                                delete-message="It will be moved to the Trash, where it can be restored."
                                size="sm"
                                :editRoute="route('admin.blog-categories.edit', $category)"
                                :canEdit="$canEdit"
                                :deleteRoute="route('admin.blog-categories.destroy', $category)"
                                :deleteId="$category->id"
                                :canDelete="$canDelete"
                            />
                        </td>
                    @endif
                </tr>
            @endforeach
        </x-admin.table>
    </x-admin.bulk>
</x-admin>
