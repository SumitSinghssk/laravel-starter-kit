@php
    $canEdit = auth()
        ->user()
        ->can('admin.seo.edit');
    $canDelete = auth()
        ->user()
        ->can('admin.seo.delete');
    $canManage = $canEdit || $canDelete;

    $headers = ['Page & Info', 'SEO Details'];
    if ($canManage) {
        $headers[] = 'Actions';
    }

    $bulk = \App\Http\Controllers\Admin\BulkActionController::allowed('seo', auth()->user());
    if ($bulk) {
        array_unshift($headers, ['select' => true]);
    }
@endphp

<x-admin :breadcrumb="[
    ['label' => 'SEO Settings', 'url' => route('admin.seo.index')]
]">
    <x-admin.page-header
        title="SEO settings"
        description="Meta titles, descriptions and social images for each page of your site."
        icon="globe"
        :count="$seos->total()"
    >
        <x-slot:actions>
            <x-admin.transfer-actions type="seo" />
            @can('admin.seo.create')
                <x-admin.button :href="route('admin.seo.create')" icon="plus">New SEO record</x-admin.button>
            @endcan
        </x-slot>
    </x-admin.page-header>

    <x-admin.bulk type="seo" :ids="$seos->pluck('id')">
        <x-admin.table :headers="$headers" :data="$seos" emptyMessage="No SEO records found" emptyIcon="globe">
            <x-slot:toolbar>
                @include('admin.seo.partials.filters')
            </x-slot>

            @foreach ($seos as $seo)
                <tr>
                    @if ($bulk)
                        <x-admin.bulk.checkbox :value="$seo->id" :label="$seo->page" />
                    @endif

                    <td>
                        <div class="flex items-center gap-3">
                            <span
                                class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-slate-500 ring-1 ring-slate-200/80 dark:bg-slate-800 dark:text-slate-400 dark:ring-slate-700"
                            >
                                <x-admin.icon :name="$seo->slug === 'default-seo' ? 'sparkles' : 'globe'" class="h-4 w-4" />
                            </span>

                            <div class="min-w-48">
                                <div class="flex min-w-0 items-center gap-1.5">
                                    @if ($canEdit)
                                        <a
                                            href="{{ route('admin.seo.edit', $seo) }}"
                                            class="line-clamp-1 font-medium text-slate-900 hover:text-blue-600 dark:text-white dark:hover:text-blue-400"
                                        >
                                            {{ $seo->page }}
                                        </a>
                                    @else
                                        <span class="line-clamp-1 font-medium text-slate-900 dark:text-white">{{ $seo->page }}</span>
                                    @endif
                                    @if ($seo->slug === 'default-seo')
                                        <x-admin.status-badge tone="brand" label="Default" />
                                    @endif
                                </div>
                                <span class="mt-0.5 flex items-center gap-1 text-xs text-slate-400">
                                    <x-admin.icon name="link" class="h-3 w-3 shrink-0" />
                                    <span class="truncate">{{ $seo->slug }}</span>
                                </span>
                            </div>
                        </div>
                    </td>

                    <td>
                        <div class="flex max-w-md min-w-64 flex-col gap-0.5">
                            <p class="line-clamp-1 text-slate-700 dark:text-slate-200">
                                {{ $seo->meta_title ?: '—' }}
                            </p>
                            @if ($seo->meta_description)
                                <p class="line-clamp-1 text-xs text-slate-400" title="{{ $seo->meta_description }}">
                                    {{ Str::limit($seo->meta_description, 80) }}
                                </p>
                            @endif

                            <span class="mt-1 flex flex-wrap items-center gap-1.5">
                                @if ($seo->index)
                                    <x-admin.status-badge tone="success" label="Indexed" />
                                @else
                                    <x-admin.status-badge tone="neutral" label="No index" />
                                @endif
                                @if ($seo->og_image)
                                    <span
                                        class="inline-flex items-center gap-1 rounded-md bg-slate-100 px-1.5 py-0.5 text-[11px] font-medium text-slate-500 dark:bg-slate-800 dark:text-slate-400"
                                        title="Has a social sharing image"
                                    >
                                        <x-admin.icon name="image" class="h-3 w-3" />
                                        Social image
                                    </span>
                                @endif
                            </span>
                        </div>
                    </td>

                    @if ($canManage)
                        <td>
                            <x-admin.row-actions
                                size="sm"
                                :editRoute="route('admin.seo.edit', $seo)"
                                :canEdit="$canEdit"
                                :deleteRoute="route('admin.seo.destroy', $seo)"
                                :deleteId="$seo->id"
                                :canDelete="$canDelete"
                            />
                        </td>
                    @endif
                </tr>
            @endforeach
        </x-admin.table>
    </x-admin.bulk>
</x-admin>
