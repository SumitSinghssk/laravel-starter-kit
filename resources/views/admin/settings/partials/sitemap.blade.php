@php
    $canUpdate = auth()
        ->user()
        ->can('admin.settings.sitemap.update');
    $canView = auth()
        ->user()
        ->can('admin.settings.sitemap.view');

    $stats = [
        ['label' => 'Last generated', 'value' => $sitemapInfo['lastGenerated'] ?? 'Never', 'icon' => 'clock'],
        ['label' => 'File size', 'value' => $sitemapInfo['fileSize'] ?? 'N/A', 'icon' => 'file'],
        ['label' => 'Total URLs', 'value' => $sitemapInfo['totalUrls'] ?? 'N/A', 'icon' => 'link'],
    ];
@endphp

<div class="space-y-6">
    <x-admin.card title="Sitemap status" text="Overview of the sitemap.xml file search engines read." icon="sitemap">
        <x-slot:extra>
            @if ($sitemapExists ?? false)
                <x-admin.status-badge tone="success" label="Active" />
            @else
                <x-admin.status-badge tone="neutral" label="Not generated" />
            @endif
        </x-slot>

        <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
            @foreach ($stats as $stat)
                <div class="rounded-lg border border-slate-200/80 bg-slate-50/60 px-4 py-3 dark:border-slate-800 dark:bg-slate-800/40">
                    <p class="flex items-center gap-1.5 text-xs text-slate-500 dark:text-slate-400">
                        <x-admin.icon :name="$stat['icon']" class="h-3.5 w-3.5" />
                        {{ $stat['label'] }}
                    </p>
                    <p class="tabular mt-1 truncate text-sm font-semibold text-slate-900 dark:text-white">{{ $stat['value'] }}</p>
                </div>
            @endforeach
        </div>

        @if ($sitemapExists ?? false)
            <div class="mt-3 flex flex-wrap items-center justify-between gap-2 rounded-lg border border-slate-200/80 px-4 py-3 dark:border-slate-800">
                <div class="min-w-0">
                    <p class="text-xs text-slate-500 dark:text-slate-400">Public URL</p>
                    <a
                        href="{{ url('sitemap.xml') }}"
                        target="_blank"
                        class="mt-0.5 inline-flex max-w-full items-center gap-1.5 text-sm font-medium text-blue-600 hover:underline dark:text-blue-400"
                    >
                        <span class="truncate">{{ url('sitemap.xml') }}</span>
                        <x-admin.icon name="external-link" class="h-3.5 w-3.5" />
                    </a>
                </div>
            </div>
        @endif
    </x-admin.card>

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
        @if ($canUpdate)
            <x-admin.card title="Generate sitemap" text="Build it automatically from your pages and posts." icon="sparkles">
                <div class="flex h-full flex-col gap-4" x-data="{ submitting: false }">
                    <p class="text-sm text-slate-600 dark:text-slate-400">
                        Builds a fresh
                        <code class="rounded bg-slate-100 px-1 font-mono text-xs dark:bg-slate-800">sitemap.xml</code>
                        from your site's pages and posts, replacing the current file.
                    </p>

                    <form action="{{ route('admin.settings.sitemap.generate') }}" method="POST" x-on:submit="submitting = true">
                        @csrf
                        <x-admin.button type="submit" x-bind:disabled="submitting">
                            <span x-show="!submitting" class="flex items-center gap-1.5">
                                <x-admin.icon name="refresh" class="h-4 w-4" />
                                Generate sitemap
                            </span>
                            <span x-show="submitting" x-cloak class="flex items-center gap-1.5">
                                <x-admin.icon name="refresh" class="h-4 w-4 animate-spin" />
                                Generating…
                            </span>
                        </x-admin.button>
                    </form>
                </div>
            </x-admin.card>
        @endif

        @if ($sitemapExists ?? false)
            <x-admin.card title="Download sitemap" text="Save a local copy of the current sitemap.xml." icon="download">
                <div
                    class="flex flex-col gap-4"
                    x-data="{
                        submitting: false,
                        download() {
                            this.submitting = true
                            fetch('{{ route('admin.settings.sitemap.download') }}')
                                .then((res) => {
                                    if (! res.ok) throw new Error('Server error')
                                    this.submitting = false
                                    return res
                                        .blob()
                                        .then((blob) => ({ blob, filename: 'sitemap.xml' }))
                                })
                                .then(({ blob, filename }) => {
                                    const a = document.createElement('a')
                                    a.href = URL.createObjectURL(blob)
                                    a.download = filename
                                    document.body.appendChild(a)
                                    a.click()
                                    a.remove()
                                    URL.revokeObjectURL(a.href)
                                })
                                .catch((err) => {
                                    this.submitting = false
                                    alert('Download failed: ' + err.message)
                                })
                        },
                    }"
                >
                    <p class="text-sm text-slate-600 dark:text-slate-400">Useful as a backup before regenerating or uploading a replacement.</p>

                    <div>
                        <x-admin.button type="button" variant="secondary" x-on:click="download()" x-bind:disabled="submitting">
                            <span x-show="!submitting" class="flex items-center gap-1.5">
                                <x-admin.icon name="download" class="h-4 w-4" />
                                Download sitemap.xml
                            </span>
                            <span x-show="submitting" x-cloak class="flex items-center gap-1.5">
                                <x-admin.icon name="refresh" class="h-4 w-4 animate-spin" />
                                Downloading…
                            </span>
                        </x-admin.button>
                    </div>
                </div>
            </x-admin.card>
        @endif
    </div>

    @if ($canUpdate)
        <x-admin.card title="Upload sitemap" text="Replace the current sitemap with your own XML file (max 10 MB)." icon="upload-cloud">
            <div
                x-data="{
                    file: null,
                    uploading: false,
                    dragover: false,
                    setFile(f) {
                        if (f && f.name.endsWith('.xml')) {
                            this.file = f
                        } else if (f) {
                            alert('Only .xml files are accepted.')
                        }
                    },
                }"
            >
                <form
                    action="{{ route('admin.settings.sitemap.upload') }}"
                    method="POST"
                    enctype="multipart/form-data"
                    x-on:submit="uploading = true"
                    class="space-y-4"
                >
                    @csrf

                    <label
                        class="relative flex h-36 w-full cursor-pointer flex-col items-center justify-center rounded-xl border-2 border-dashed transition focus-within:ring-3 focus-within:ring-blue-500/30"
                        :class="dragover ? 'border-blue-400 bg-blue-50 dark:border-blue-500 dark:bg-blue-500/10' : 'border-slate-300 bg-slate-50 hover:border-blue-400 hover:bg-blue-50/40 dark:border-slate-700 dark:bg-slate-800/40 dark:hover:border-blue-500/60 dark:hover:bg-slate-800'"
                        x-on:dragover.prevent="dragover = true"
                        x-on:dragleave.prevent="dragover = false"
                        x-on:drop.prevent="
                            dragover = false
                            setFile($event.dataTransfer.files[0])
                        "
                    >
                        <div class="pointer-events-none relative z-10 flex w-full flex-col items-center justify-center px-4">
                            <template x-if="!file">
                                <div class="flex flex-col items-center gap-2 text-center">
                                    <span
                                        class="flex h-10 w-10 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-400 shadow-xs dark:border-slate-700 dark:bg-slate-900"
                                    >
                                        <x-admin.icon name="upload-cloud" class="h-5 w-5" />
                                    </span>
                                    <div>
                                        <p class="text-sm text-slate-600 dark:text-slate-300">
                                            <span class="font-medium text-blue-600 dark:text-blue-400">Click to upload</span>
                                            or drag &amp; drop
                                        </p>
                                        <p class="text-xs text-slate-400">XML files only · max 10 MB</p>
                                    </div>
                                </div>
                            </template>

                            <template x-if="file">
                                <div class="flex flex-col items-center gap-2 text-center">
                                    <span
                                        class="flex h-10 w-10 items-center justify-center rounded-lg border border-blue-100 bg-blue-50 text-blue-600 dark:border-blue-500/20 dark:bg-blue-500/10 dark:text-blue-300"
                                    >
                                        <x-admin.icon name="file-text" class="h-5 w-5" />
                                    </span>
                                    <p class="max-w-full truncate text-sm font-medium text-slate-800 dark:text-slate-200" x-text="file.name"></p>
                                    <button
                                        type="button"
                                        x-on:click.stop.prevent="file = null"
                                        class="pointer-events-auto inline-flex cursor-pointer items-center gap-1 rounded-md px-1.5 py-0.5 text-xs font-medium text-red-600 hover:bg-red-50 focus:outline-none focus-visible:ring-3 focus-visible:ring-red-500/30 dark:text-red-400 dark:hover:bg-red-500/10"
                                    >
                                        <x-admin.icon name="x" class="h-3.5 w-3.5" />
                                        Remove
                                    </button>
                                </div>
                            </template>
                        </div>

                        <input
                            type="file"
                            name="sitemap"
                            accept=".xml"
                            aria-label="Choose a sitemap XML file"
                            class="absolute inset-0 z-0 cursor-pointer opacity-0"
                            x-on:change="setFile($event.target.files[0])"
                        />
                    </label>

                    @error('sitemap')
                        <p class="flex items-center gap-1.5 text-xs text-red-600 dark:text-red-400">
                            <x-admin.icon name="alert-circle" class="h-3.5 w-3.5" />
                            {{ $message }}
                        </p>
                    @enderror

                    <div x-show="file" x-cloak>
                        <x-admin.button type="submit" x-bind:disabled="uploading">
                            <span x-show="!uploading" class="flex items-center gap-1.5">
                                <x-admin.icon name="upload" class="h-4 w-4" />
                                Upload sitemap
                            </span>
                            <span x-show="uploading" x-cloak class="flex items-center gap-1.5">
                                <x-admin.icon name="refresh" class="h-4 w-4 animate-spin" />
                                Uploading…
                            </span>
                        </x-admin.button>
                    </div>
                </form>
            </div>
        </x-admin.card>
    @endif
</div>
