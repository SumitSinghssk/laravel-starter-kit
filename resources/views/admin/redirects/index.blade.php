@php
    $canEdit = auth()
        ->user()
        ->can('admin.redirects.edit');
    $canDelete = auth()
        ->user()
        ->can('admin.redirects.delete');
    $canToggle = auth()
        ->user()
        ->can('admin.redirects.toogle-status');
    $canManage = $canEdit || $canDelete;

    $headers = ['Redirect', 'Type', 'Hits', 'Status'];
    if ($canManage) {
        $headers[] = 'Actions';
    }

    $bulk = \App\Http\Controllers\Admin\BulkActionController::allowed('redirects', auth()->user());
    if ($bulk) {
        array_unshift($headers, ['select' => true]);
    }

    $permanent = [301, 308];
@endphp

<x-admin :breadcrumb="[
    ['label' => 'Redirects', 'url' => route('admin.redirects.index')]
]">
    <x-admin.page-header
        title="Redirects"
        description="Send visitors and search engines from old URLs to new ones."
        icon="redirect"
        :count="$redirects->total()"
    >
        <x-slot:actions>
            <x-admin.transfer-actions type="redirects" />
            @can('admin.redirects.create')
                <x-admin.button :href="route('admin.redirects.create')" icon="plus">New redirect</x-admin.button>
            @endcan
        </x-slot>
    </x-admin.page-header>

    <x-admin.bulk type="redirects" :ids="$redirects->pluck('id')">
        <x-admin.table :headers="$headers" :data="$redirects" emptyMessage="No redirects found" emptyIcon="redirect">
            <x-slot:toolbar>
                @include('admin.redirects.partials.filters')
            </x-slot>

            @foreach ($redirects as $redirect)
                <tr>
                    @if ($bulk)
                        <x-admin.bulk.checkbox :value="$redirect->id" :label="$redirect->source_path" />
                    @endif

                    <td class="max-w-xl">
                        <div class="min-w-0 space-y-1">
                            @if ($canEdit)
                                <a
                                    href="{{ route('admin.redirects.edit', $redirect) }}"
                                    class="block truncate font-mono text-[13px] font-medium text-slate-900 hover:text-blue-600 dark:text-white dark:hover:text-blue-400"
                                >
                                    {{ $redirect->source_path }}
                                </a>
                            @else
                                <span class="block truncate font-mono text-[13px] font-medium text-slate-900 dark:text-white">
                                    {{ $redirect->source_path }}
                                </span>
                            @endif

                            <span class="flex min-w-0 items-center gap-1.5 text-xs text-slate-500 dark:text-slate-400">
                                <x-admin.icon name="arrow-right" class="h-3 w-3 shrink-0 text-slate-400" />
                                <span class="truncate font-mono" title="{{ $redirect->target_url }}">{{ $redirect->target_url }}</span>
                                @if ($redirect->isExternal())
                                    <x-admin.icon name="external-link" class="h-3 w-3 shrink-0 text-slate-400" />
                                @endif
                            </span>

                            @if ($redirect->note)
                                <span class="block truncate text-xs text-slate-400">{{ $redirect->note }}</span>
                            @endif
                        </div>
                    </td>

                    <td class="whitespace-nowrap">
                        <span
                            @class([
                                'tabular inline-flex items-center rounded-md px-1.5 py-0.5 text-xs font-semibold',
                                'bg-blue-50 text-blue-700 dark:bg-blue-500/10 dark:text-blue-300' => in_array($redirect->status_code, $permanent),
                                'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-300' => ! in_array($redirect->status_code, $permanent),
                            ])
                            title="{{ \App\Models\Redirect::STATUS_CODES[$redirect->status_code]['description'] ?? '' }}"
                        >
                            {{ $redirect->status_code }}
                        </span>
                        <span class="ml-1 text-xs text-slate-500 dark:text-slate-400">
                            {{ in_array($redirect->status_code, $permanent) ? 'Permanent' : 'Temporary' }}
                        </span>
                    </td>

                    <td class="whitespace-nowrap">
                        <span class="tabular block text-slate-700 dark:text-slate-200">{{ number_format($redirect->hits) }}</span>
                        <span class="text-xs text-slate-400">
                            {{ $redirect->last_hit_at ? 'Last ' . $redirect->last_hit_at->diffForHumans() : 'Never used' }}
                        </span>
                    </td>

                    <td>
                        <x-admin.status-toggle
                            :url="route('admin.redirects.toggle-status', $redirect->id)"
                            :status="$redirect->status"
                            :can="$canToggle"
                        />
                    </td>

                    @if ($canManage)
                        <td>
                            <x-admin.row-actions
                                size="sm"
                                :viewRoute="url($redirect->source_path)"
                                :editRoute="route('admin.redirects.edit', $redirect)"
                                :canEdit="$canEdit"
                                :deleteRoute="route('admin.redirects.destroy', $redirect)"
                                :deleteId="$redirect->id"
                                :canDelete="$canDelete"
                            />
                        </td>
                    @endif
                </tr>
            @endforeach
        </x-admin.table>
    </x-admin.bulk>
</x-admin>
