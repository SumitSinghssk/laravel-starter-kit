@php
    $canEdit = auth()
        ->user()
        ->can('admin.galleries.edit');
    $canDelete = auth()
        ->user()
        ->can('admin.galleries.delete');
    $canToggle = auth()
        ->user()
        ->can('admin.galleries.toogle-status');
    $filtered = collect(request()->only(['search', 'status', 'featured']))
        ->filter()
        ->isNotEmpty();
@endphp

<x-admin :breadcrumb="[
    ['label' => 'Gallery', 'url' => route('admin.galleries.index')]
]">
    <x-admin.page-header title="Gallery" description="Photo and video albums: uploads and YouTube links." icon="image" :count="$galleries->total()">
        @can('admin.galleries.create')
            <x-slot:actions>
                <x-admin.button :href="route('admin.galleries.create')" icon="plus">New album</x-admin.button>
            </x-slot>
        @endcan
    </x-admin.page-header>

    <div class="rounded-xl border border-slate-200/80 bg-white shadow-xs dark:border-slate-800 dark:bg-slate-900">
        <div class="border-b border-slate-100 p-3 dark:border-slate-800">
            <x-admin.filter.bar :action="route('admin.galleries.index')" search="Search albums…">
                <x-admin.filter.select name="featured" label="Featured" icon="sparkles" :options="['yes' => 'Featured', 'no' => 'Not featured']" />
                <x-admin.filter.select name="status" label="Status" icon="circle-dot" :options="\App\Enums\CommonStatusEnum::dotOptions()" />
            </x-admin.filter.bar>
        </div>

        @if ($galleries->isEmpty())
            <div class="flex flex-col items-center justify-center px-6 py-16 text-center">
                <span
                    class="mb-3 flex h-11 w-11 items-center justify-center rounded-xl border border-slate-200 bg-slate-50 text-slate-400 dark:border-slate-700 dark:bg-slate-800"
                >
                    <x-admin.icon :name="$filtered ? 'search' : 'image'" class="h-5 w-5" />
                </span>
                <h3 class="text-sm font-semibold text-slate-900 dark:text-white">{{ $filtered ? 'No albums match' : 'No albums yet' }}</h3>
                <p class="mt-1 max-w-sm text-sm text-slate-500 dark:text-slate-400">
                    {{ $filtered ? 'Try changing or clearing the filters.' : 'Create an album, then add photos, videos and YouTube links to it.' }}
                </p>
                @if (! $filtered)
                    @can('admin.galleries.create')
                        <x-admin.button :href="route('admin.galleries.create')" icon="plus" class="mt-4">New album</x-admin.button>
                    @endcan
                @endif
            </div>
        @else
            <ul class="grid grid-cols-1 gap-4 p-4 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-4">
                @foreach ($galleries as $gallery)
                    @php
                        $cover = $gallery->cover();
                        $url = $canEdit ? route('admin.galleries.edit', $gallery) : null;
                    @endphp

                    <li
                        class="group relative flex flex-col overflow-hidden rounded-xl border border-slate-200 bg-white transition hover:border-slate-300 hover:shadow-md dark:border-slate-800 dark:bg-slate-900 dark:hover:border-slate-700"
                    >
                        <a
                            @if ($url) href="{{ $url }}" @endif
                            class="relative block aspect-16/10 overflow-hidden bg-slate-100 dark:bg-slate-800"
                            @unless ($url) aria-disabled="true" @endunless
                        >
                            @if ($cover?->thumbnail_url)
                                <img
                                    src="{{ $cover->thumbnail_url }}"
                                    alt=""
                                    loading="lazy"
                                    decoding="async"
                                    class="h-full w-full object-cover transition duration-300 group-hover:scale-[1.03]"
                                />
                            @else
                                <span class="flex h-full items-center justify-center text-slate-300 dark:text-slate-600">
                                    <x-admin.icon :name="$cover ? 'film' : 'image'" class="h-10 w-10" stroke="1.3" />
                                </span>
                            @endif

                            <span class="absolute bottom-2 left-2 flex gap-1.5">
                                <span
                                    class="inline-flex items-center gap-1 rounded-md bg-slate-950/70 px-1.5 py-0.5 text-[11px] font-medium text-white backdrop-blur-sm"
                                >
                                    <x-admin.icon name="image" class="h-3 w-3" />
                                    {{ $gallery->images_count }}
                                </span>
                                @if ($gallery->videos_count)
                                    <span
                                        class="inline-flex items-center gap-1 rounded-md bg-slate-950/70 px-1.5 py-0.5 text-[11px] font-medium text-white backdrop-blur-sm"
                                    >
                                        <x-admin.icon name="play" class="h-3 w-3" />
                                        {{ $gallery->videos_count }}
                                    </span>
                                @endif
                            </span>

                            @if ($gallery->is_featured)
                                <span
                                    class="absolute top-2 left-2 inline-flex items-center gap-1 rounded-full bg-amber-400 px-2 py-0.5 text-[11px] font-semibold text-amber-950"
                                >
                                    <x-admin.icon name="star" class="h-3 w-3" />
                                    Featured
                                </span>
                            @endif
                        </a>

                        <div class="flex flex-1 flex-col gap-3 p-3.5">
                            <div class="min-w-0">
                                @if ($url)
                                    <a
                                        href="{{ $url }}"
                                        class="line-clamp-1 font-semibold text-slate-900 hover:text-blue-600 dark:text-white dark:hover:text-blue-400"
                                    >
                                        {{ $gallery->title }}
                                    </a>
                                @else
                                    <span class="line-clamp-1 font-semibold text-slate-900 dark:text-white">{{ $gallery->title }}</span>
                                @endif
                                <p class="mt-0.5 flex items-center gap-1.5 text-xs text-slate-500 dark:text-slate-400">
                                    @if ($gallery->event_date)
                                        <x-admin.icon name="calendar" class="h-3 w-3" />
                                        {{ local_day($gallery->event_date) }}
                                        <span aria-hidden="true">·</span>
                                    @endif

                                    {{ $gallery->items_count }} {{ Str::plural('item', $gallery->items_count) }}
                                </p>
                            </div>

                            <div class="mt-auto flex items-center justify-between gap-2">
                                <x-admin.status-toggle
                                    :url="route('admin.galleries.toggle-status', $gallery->id)"
                                    :status="$gallery->status"
                                    :can="$canToggle"
                                />

                                @if ($canEdit || $canDelete)
                                    <x-admin.row-actions
                                        size="sm"
                                        :editRoute="route('admin.galleries.edit', $gallery)"
                                        :canEdit="$canEdit"
                                        :deleteRoute="route('admin.galleries.destroy', $gallery)"
                                        :deleteId="$gallery->id"
                                        :canDelete="$canDelete"
                                    />
                                @endif
                            </div>
                        </div>
                    </li>
                @endforeach
            </ul>

            @if ($galleries->hasPages())
                <div class="border-t border-slate-100 px-4 py-3 dark:border-slate-800">
                    {{ $galleries->links('vendor.pagination.tailwind') }}
                </div>
            @endif
        @endif
    </div>
</x-admin>
