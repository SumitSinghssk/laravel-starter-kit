@php
    $canEdit = auth()
        ->user()
        ->can('admin.testimonials.edit');
    $canDelete = auth()
        ->user()
        ->can('admin.testimonials.delete');
    $canToggle = auth()
        ->user()
        ->can('admin.testimonials.toogle-status');
    $canManage = $canEdit || $canDelete;

    $headers = ['Client', 'Testimonial', 'Rating', 'Order', 'Status'];
    if ($canManage) {
        $headers[] = 'Actions';
    }

    $bulk = \App\Http\Controllers\Admin\BulkActionController::allowed('testimonials', auth()->user());
    if ($bulk) {
        array_unshift($headers, ['select' => true]);
    }
@endphp

<x-admin :breadcrumb="[
    ['label' => 'Testimonials', 'url' => route('admin.testimonials.index')]
]">
    <x-admin.page-header
        title="Testimonials"
        description="What your clients say about you, ready to show on the website."
        icon="message"
        :count="$testimonials->total()"
    >
        <x-slot:actions>
            <x-admin.trash-link type="testimonials" />
            <x-admin.transfer-actions type="testimonials" />
            @can('admin.testimonials.create')
                <x-admin.button :href="route('admin.testimonials.create')" icon="plus">New testimonial</x-admin.button>
            @endcan
        </x-slot>
    </x-admin.page-header>

    <x-admin.bulk type="testimonials" :ids="$testimonials->pluck('id')">
        <x-admin.table :headers="$headers" :data="$testimonials" emptyMessage="No testimonials found" emptyIcon="message">
            <x-slot:toolbar>
                @include('admin.testimonials.partials.filters')
            </x-slot>

            @foreach ($testimonials as $testimonial)
                <tr>
                    @if ($bulk)
                        <x-admin.bulk.checkbox :value="$testimonial->id" :label="$testimonial->name" />
                    @endif

                    <td class="max-w-xs">
                        <div class="flex items-center gap-3">
                            <x-admin.thumb :src="$testimonial->photo_url" icon="user" class="h-9 w-9 rounded-full" />

                            <div class="min-w-0">
                                <span class="flex items-center gap-1.5">
                                    @if ($canEdit)
                                        <a
                                            href="{{ route('admin.testimonials.edit', $testimonial) }}"
                                            class="truncate font-medium text-slate-900 hover:text-blue-600 dark:text-white dark:hover:text-blue-400"
                                        >
                                            {{ $testimonial->name }}
                                        </a>
                                    @else
                                        <span class="truncate font-medium text-slate-900 dark:text-white">{{ $testimonial->name }}</span>
                                    @endif

                                    @if ($testimonial->is_featured)
                                        <span
                                            class="inline-flex shrink-0 items-center gap-1 rounded-full bg-amber-50 px-1.5 py-px text-[11px] font-semibold text-amber-700 dark:bg-amber-500/10 dark:text-amber-300"
                                            title="Shown first on the website"
                                        >
                                            <x-admin.icon name="star" class="h-3 w-3" />
                                            Featured
                                        </span>
                                    @endif
                                </span>
                                @if ($testimonial->by_line)
                                    <span class="block truncate text-xs text-slate-500 dark:text-slate-400">{{ $testimonial->by_line }}</span>
                                @endif
                            </div>
                        </div>
                    </td>

                    <td class="max-w-md">
                        <p class="line-clamp-2 text-slate-600 dark:text-slate-300" title="{{ $testimonial->quote }}">“{{ $testimonial->quote }}”</p>
                    </td>

                    <td class="whitespace-nowrap">
                        @if ($testimonial->rating)
                            <span class="flex items-center gap-0.5" title="{{ $testimonial->rating }} / 5">
                                @for ($i = 1; $i <= 5; $i++)
                                    <x-admin.icon
                                        name="star"
                                        :class="\Illuminate\Support\Arr::toCssClasses([
                                            'h-3.5 w-3.5',
                                            'fill-amber-400 text-amber-400' => $i <= $testimonial->rating,
                                            'text-slate-300 dark:text-slate-600' => $i > $testimonial->rating,
                                        ])"
                                    />
                                @endfor
                            </span>
                        @else
                            <span class="text-slate-400">—</span>
                        @endif
                    </td>

                    <td class="tabular whitespace-nowrap text-slate-600 dark:text-slate-300">{{ $testimonial->sort_order }}</td>

                    <td>
                        <x-admin.status-toggle
                            :url="route('admin.testimonials.toggle-status', $testimonial->id)"
                            :status="$testimonial->status"
                            :can="$canToggle"
                        />
                    </td>

                    @if ($canManage)
                        <td>
                            <x-admin.row-actions
                                delete-message="It will be moved to the Trash, where it can be restored."
                                size="sm"
                                :editRoute="route('admin.testimonials.edit', $testimonial)"
                                :canEdit="$canEdit"
                                :deleteRoute="route('admin.testimonials.destroy', $testimonial)"
                                :deleteId="$testimonial->id"
                                :canDelete="$canDelete"
                            />
                        </td>
                    @endif
                </tr>
            @endforeach
        </x-admin.table>
    </x-admin.bulk>
</x-admin>
