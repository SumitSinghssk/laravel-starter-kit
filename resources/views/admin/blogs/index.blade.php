@php
    $canEdit = auth()
        ->user()
        ->can('admin.blogs.edit');
    $canDelete = auth()
        ->user()
        ->can('admin.blogs.delete');
    $canToggle = auth()
        ->user()
        ->can('admin.blogs.toogle-status');
    $canManage = $canEdit || $canDelete;

    $headers = ['Post', 'Categories', 'Status', 'Published', 'Author'];
    if ($canManage) {
        $headers[] = 'Actions';
    }

    $bulk = \App\Http\Controllers\Admin\BulkActionController::allowed('blogs', auth()->user());
    if ($bulk) {
        array_unshift($headers, ['select' => true]);
    }
@endphp

<x-admin :breadcrumb="[
    ['label' => 'Blogs', 'url' => route('admin.blogs.index')]
]">
    <x-admin.page-header
        title="Blog posts"
        description="Write, schedule and organise the articles on your blog."
        icon="newspaper"
        :count="$blogs->total()"
    >
        <x-slot:actions>
            <x-admin.trash-link type="blogs" />
            <x-admin.transfer-actions type="blogs" />
            @can('admin.blogs.create')
                <x-admin.button :href="route('admin.blogs.create')" icon="plus">New post</x-admin.button>
            @endcan
        </x-slot>
    </x-admin.page-header>

    <x-admin.bulk type="blogs" :ids="$blogs->pluck('id')">
        <x-admin.table :headers="$headers" :data="$blogs" emptyMessage="No blog posts found" emptyIcon="newspaper">
            <x-slot:toolbar>
                @include('admin.blogs.partials.filters')
            </x-slot>

            @foreach ($blogs as $blog)
                <tr>
                    @if ($bulk)
                        <x-admin.bulk.checkbox :value="$blog->id" :label="$blog->title" />
                    @endif

                    <td class="max-w-md">
                        <div class="flex items-center gap-3">
                            <x-admin.thumb :src="$blog->featured_image ? asset('storage/' . $blog->featured_image) : null" class="h-10 w-14" />

                            <div class="min-w-0">
                                @if ($canEdit)
                                    <a
                                        href="{{ route('admin.blogs.edit', $blog) }}"
                                        class="line-clamp-1 font-medium text-slate-900 hover:text-blue-600 dark:text-white dark:hover:text-blue-400"
                                    >
                                        {{ $blog->title }}
                                    </a>
                                @else
                                    <span class="line-clamp-1 font-medium text-slate-900 dark:text-white">{{ $blog->title }}</span>
                                @endif
                                <span class="mt-0.5 flex items-center gap-1 text-xs text-slate-400">
                                    <x-admin.icon name="link" class="h-3 w-3" />
                                    <span class="truncate">/{{ $blog->slug }}</span>
                                </span>
                            </div>
                        </div>
                    </td>

                    <td>
                        <div class="flex flex-wrap gap-1">
                            @forelse ($blog->categories->take(2) as $category)
                                <span
                                    class="inline-flex items-center gap-1 rounded-md border border-slate-200 bg-white px-1.5 py-0.5 text-xs font-medium whitespace-nowrap text-slate-600 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300"
                                >
                                    <x-admin.icon name="tag" class="h-3 w-3 text-slate-400" />
                                    {{ $category->name }}
                                </span>
                            @empty
                                <span class="text-xs text-slate-400">—</span>
                            @endforelse

                            @if ($blog->categories->count() > 2)
                                <span
                                    class="inline-flex items-center rounded-md bg-slate-100 px-1.5 py-0.5 text-xs font-medium text-slate-500 dark:bg-slate-800 dark:text-slate-400"
                                    title="{{ $blog->categories->skip(2)->pluck('name')->implode(', ') }}"
                                >
                                    +{{ $blog->categories->count() - 2 }}
                                </span>
                            @endif
                        </div>
                    </td>

                    <td>
                        <x-admin.status-toggle :url="route('admin.blogs.toggle-status', $blog->id)" :status="$blog->status" :can="$canToggle" />
                    </td>

                    <td class="whitespace-nowrap">
                        @if ($blog->published_at)
                            <span class="block text-slate-700 dark:text-slate-200">{{ local_date($blog->published_at) }}</span>
                            <span class="text-xs text-slate-400">
                                {{ $blog->published_at->isFuture() ? 'Scheduled' : $blog->published_at->diffForHumans() }}
                            </span>
                        @else
                            <span class="text-slate-400">—</span>
                        @endif
                    </td>

                    <td class="whitespace-nowrap">
                        @if ($blog->author)
                            <span class="flex items-center gap-2">
                                <img src="{{ $blog->author->avatar_url }}" alt="" class="h-6 w-6 rounded-full object-cover" />
                                <span class="text-slate-700 dark:text-slate-200">{{ $blog->author->name }}</span>
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
                                :editRoute="route('admin.blogs.edit', $blog)"
                                :canEdit="$canEdit"
                                :deleteRoute="route('admin.blogs.destroy', $blog)"
                                :deleteId="$blog->id"
                                :canDelete="$canDelete"
                            />
                        </td>
                    @endif
                </tr>
            @endforeach
        </x-admin.table>
    </x-admin.bulk>
</x-admin>
