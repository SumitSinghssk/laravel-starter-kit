@php
    use Illuminate\Support\Number;

    $canUpload = auth()
        ->user()
        ->can('admin.media.upload');
    $libraryConfig = [
        'urls' => [
            'upload' => route('admin.media-library.uploads.store'),
            'rescan' => route('admin.media-library.rescan'),
        ],
        'stale' => $stale,
        'maxSize' => config('media.max_size'),
        'defaultFolder' => config('media.upload_folder'),
    ];
    $filtered = collect(request()->only(['search', 'location', 'type', 'usage', 'size', 'folder']))
        ->filter()
        ->isNotEmpty();
    $largeLabel = Number::fileSize(config('media.large_bytes'));
    $statTiles = [
        ['label' => 'Images', 'value' => number_format($stats['total']), 'icon' => 'image', 'tone' => 'bg-blue-50 text-blue-600 dark:bg-blue-500/10 dark:text-blue-300', 'url' => route('admin.media-library.index')],
        ['label' => 'Total size', 'value' => Number::fileSize($stats['bytes'], 1), 'icon' => 'folder', 'tone' => 'bg-violet-50 text-violet-600 dark:bg-violet-500/10 dark:text-violet-300', 'url' => route('admin.media-library.index', ['sort' => 'largest'])],
        ['label' => 'Unused', 'value' => number_format($stats['unused']), 'icon' => 'circle-dashed', 'tone' => 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300', 'url' => route('admin.media-library.index', ['usage' => 'unused'])],
        ['label' => "Over {$largeLabel}", 'value' => number_format($stats['large']), 'icon' => 'alert-triangle', 'tone' => 'bg-amber-50 text-amber-600 dark:bg-amber-500/10 dark:text-amber-300', 'url' => route('admin.media-library.index', ['size' => 'large', 'sort' => 'largest'])],
    ];
@endphp

<x-admin :breadcrumb="[
    ['label' => 'Media Library', 'url' => route('admin.media-library.index')]
]">
    <div x-data="mediaLibrary(@js($libraryConfig))" x-on:keydown.escape.window="confirming ? (confirming = null) : close()">
        <x-admin.page-header
            title="Media Library"
            description="Every image on your website. Replace one here and it updates everywhere it is used."
            icon="folder"
            :count="$stats['total']"
        >
            <x-slot:actions>
                <x-admin.button
                    type="button"
                    variant="secondary"
                    icon="refresh"
                    class="min-w-28"
                    x-on:click="rescan()"
                    x-bind:disabled="scanning"
                    x-bind:class="scanning && '[&>svg]:animate-spin'"
                >
                    <span x-text="scanning ? 'Scanning…' : 'Rescan'">Rescan</span>
                </x-admin.button>
                @if ($canUpload)
                    <x-admin.button type="button" icon="upload" x-on:click="uploadOpen = ! uploadOpen">Upload images</x-admin.button>
                @endif
            </x-slot>
        </x-admin.page-header>

        <div
            x-show="changesFound"
            x-cloak
            x-transition
            class="mb-5 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-800 dark:border-blue-500/30 dark:bg-blue-500/10 dark:text-blue-200"
        >
            <span class="flex items-center gap-2">
                <x-admin.icon name="info" class="h-4 w-4" />
                New or changed images were found on disk.
            </span>
            <x-admin.button type="button" size="sm" x-on:click="reload()">Refresh list</x-admin.button>
        </div>

        <div class="mb-5 grid grid-cols-2 gap-3 lg:grid-cols-4">
            @foreach ($statTiles as $tile)
                <a
                    href="{{ $tile['url'] }}"
                    class="flex items-center gap-3 rounded-xl border border-slate-200/80 bg-white p-3.5 shadow-xs transition hover:border-slate-300 dark:border-slate-800 dark:bg-slate-900 dark:hover:border-slate-700"
                >
                    <span class="{{ $tile['tone'] }} flex h-9 w-9 shrink-0 items-center justify-center rounded-lg">
                        <x-admin.icon :name="$tile['icon']" class="h-4.5 w-4.5" />
                    </span>
                    <span class="min-w-0">
                        <span class="tabular block text-lg leading-tight font-semibold text-slate-900 dark:text-white">{{ $tile['value'] }}</span>
                        <span class="block truncate text-xs text-slate-500 dark:text-slate-400">{{ $tile['label'] }}</span>
                    </span>
                </a>
            @endforeach
        </div>

        @if ($canUpload)
            @include('admin.media-library.partials.upload')
        @endif

        <div class="rounded-xl border border-slate-200/80 bg-white shadow-xs dark:border-slate-800 dark:bg-slate-900">
            <div class="border-b border-slate-100 p-3 dark:border-slate-800">
                <x-admin.filter.bar :action="route('admin.media-library.index')" search="Search by file name or folder…">
                    <x-admin.filter.select
                        name="location"
                        label="Location"
                        icon="folder"
                        :options="['storage' => 'Uploads (storage)', 'public' => 'Public folder']"
                    />
                    <x-admin.filter.select
                        name="type"
                        label="Type"
                        icon="image"
                        :options="['jpg' => 'JPG', 'png' => 'PNG', 'webp' => 'WebP', 'gif' => 'GIF', 'svg' => 'SVG', 'ico' => 'ICO']"
                    />
                    <x-admin.filter.select
                        name="usage"
                        label="Usage"
                        icon="link"
                        :options="['used' => 'Used on the site', 'unused' => 'Not used anywhere']"
                    />
                    <x-admin.filter.select name="size" label="Size" icon="alert-triangle" :options="['large' => 'Larger than ' . $largeLabel]" />
                    <x-admin.filter.select name="folder" label="Folder" icon="folder-tree" :options="$folders" searchable :menu-width="300" />
                    <x-admin.filter.select
                        name="sort"
                        label="Sort"
                        icon="sliders"
                        :options="\App\Http\Controllers\Admin\MediaLibraryController::SORTS"
                    />
                </x-admin.filter.bar>
            </div>

            @if ($files->isEmpty())
                <div class="flex flex-col items-center justify-center px-6 py-16 text-center">
                    <span
                        class="mb-3 flex h-11 w-11 items-center justify-center rounded-xl border border-slate-200 bg-slate-50 text-slate-400 dark:border-slate-700 dark:bg-slate-800"
                    >
                        <x-admin.icon :name="$filtered ? 'search' : 'image'" class="h-5 w-5" />
                    </span>
                    <h3 class="text-sm font-semibold text-slate-900 dark:text-white">{{ $filtered ? 'No images match' : 'No images yet' }}</h3>
                    <p class="mt-1 max-w-sm text-sm text-slate-500 dark:text-slate-400">
                        {{ $filtered ? 'Try changing or clearing the filters.' : 'Images you upload anywhere in the admin, and images in the public folder, appear here.' }}
                    </p>
                </div>
            @else
                <ul class="grid grid-cols-2 gap-3 p-4 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 2xl:grid-cols-6">
                    @foreach ($files as $file)
                        @include('admin.media-library.partials.card')
                    @endforeach
                </ul>

                <div
                    class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 px-4 py-3 text-xs text-slate-500 dark:border-slate-800 dark:text-slate-400"
                >
                    <span>
                        @if ($lastScan)
                            Folders checked {{ $lastScan->diffForHumans() }}
                        @endif
                    </span>
                    @if ($files->hasPages())
                        <div class="w-full sm:w-auto">{{ $files->links('vendor.pagination.tailwind') }}</div>
                    @endif
                </div>
            @endif
        </div>

        @include('admin.media-library.partials.details-panel')
    </div>
</x-admin>
