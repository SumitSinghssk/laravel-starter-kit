@php
    $label = strtolower($type->label());
@endphp

<x-admin :breadcrumb="[
    ['label' => $type->label(), 'url' => $type->listUrl()],
    ['label' => 'Import'],
]">
    <x-admin.page-header
        :title="'Import ' . $label"
        description="Add many at once from a CSV file. You check a preview before anything is saved."
        icon="upload"
        :back="$type->listUrl()"
    >
        <x-slot:actions>
            <x-admin.button variant="secondary" icon="download" :href="route('admin.transfer.template', $type->key())">
                Download template
            </x-admin.button>
        </x-slot>
    </x-admin.page-header>

    <div class="grid grid-cols-1 items-start gap-6 xl:grid-cols-[minmax(0,1fr)_22rem]">
        <div class="min-w-0 space-y-6">
            <x-admin.card title="Choose a CSV file" text="Excel: File → Save as → CSV UTF-8. Max 10 MB, 5,000 rows." icon="file-text">
                <form
                    method="POST"
                    action="{{ route('admin.transfer.store', $type->key()) }}"
                    enctype="multipart/form-data"
                    x-data="{ name: '', dragging: false, submitting: false }"
                    x-on:submit="submitting = true"
                    class="space-y-4"
                >
                    @csrf
                    <label
                        x-on:dragover.prevent="dragging = true"
                        x-on:dragleave.prevent="dragging = false"
                        x-on:drop.prevent="dragging = false; $refs.file.files = $event.dataTransfer.files; name = $event.dataTransfer.files[0]?.name ?? ''"
                        x-bind:class="
                            dragging
                                ? 'border-blue-500 bg-blue-50/70 dark:border-blue-400 dark:bg-blue-500/10'
                                : 'border-slate-300 bg-slate-50/60 hover:border-blue-400 dark:border-slate-700 dark:bg-slate-800/40'
                        "
                        class="flex cursor-pointer flex-col items-center justify-center gap-2 rounded-xl border border-dashed px-4 py-10 text-center transition"
                    >
                        <input
                            type="file"
                            name="file"
                            accept=".csv,text/csv"
                            class="sr-only"
                            x-ref="file"
                            x-on:change="name = $event.target.files[0]?.name ?? ''"
                            required
                        />
                        <span
                            class="flex h-11 w-11 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-500 shadow-xs dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300"
                        >
                            <x-admin.icon name="upload-cloud" class="h-5 w-5" />
                        </span>
                        <span class="text-sm" x-show="! name">
                            <span class="font-semibold text-blue-600 dark:text-blue-400">Choose a file</span>
                            <span class="text-slate-600 dark:text-slate-300">or drag it here</span>
                        </span>
                        <span class="text-sm font-semibold text-slate-900 dark:text-white" x-show="name" x-cloak x-text="name"></span>
                    </label>
                    <x-admin.form.error for="file" />

                    <div class="flex justify-end">
                        <x-admin.button icon="search" x-bind:disabled="! name || submitting">
                            <span x-text="submitting ? 'Checking the file…' : 'Check file'">Check file</span>
                        </x-admin.button>
                    </div>
                </form>
            </x-admin.card>

            <x-admin.card title="Columns" text="The first row of the file holds these names. Extra columns are ignored." icon="list" :padded="false">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="border-b border-slate-100 text-xs text-slate-500 dark:border-slate-800 dark:text-slate-400">
                            <tr>
                                <th class="px-4 py-2.5 font-medium">Column</th>
                                <th class="px-4 py-2.5 font-medium">What goes in it</th>
                                <th class="px-4 py-2.5 font-medium">Example</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            @foreach ($type->columns() as $name => [$description, $required, $example])
                                <tr>
                                    <td class="px-4 py-2.5 align-top whitespace-nowrap">
                                        <code class="font-mono text-xs font-semibold text-slate-900 dark:text-white">{{ $name }}</code>
                                        @if ($required)
                                            <span class="ml-1 text-xs font-semibold text-red-500" title="Required">*</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-2.5 align-top text-slate-600 dark:text-slate-300">{{ $description }}</td>
                                    <td
                                        class="max-w-56 truncate px-4 py-2.5 align-top font-mono text-xs text-slate-500 dark:text-slate-400"
                                        title="{{ $example }}"
                                    >
                                        {{ $example }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-admin.card>
        </div>

        <aside class="space-y-6">
            <x-admin.card title="How it works" icon="info">
                <ol class="space-y-3 text-sm text-slate-600 dark:text-slate-300">
                    @foreach ([
                            'Upload the file. Every row is checked; nothing is saved yet.',
                            'The preview shows which rows are new, which already exist and which have errors.',
                            'Rows that already exist on the site are never imported, and a row repeated in the file is imported once.',
                            'Confirm, and only the new rows are imported. Image URLs are downloaded and saved.'
                        ]
                        as $i => $step)
                        <li class="flex gap-3">
                            <span
                                class="tabular flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-blue-50 text-xs font-semibold text-blue-700 dark:bg-blue-500/10 dark:text-blue-300"
                            >
                                {{ $i + 1 }}
                            </span>
                            <span>{{ $step }}</span>
                        </li>
                    @endforeach
                </ol>
                <p class="mt-4 border-t border-slate-100 pt-3 text-xs text-slate-500 dark:border-slate-800 dark:text-slate-400">
                    Tip: export your {{ $label }} first: the file has exactly these columns, so you can edit it in Excel and import it on another
                    site.
                </p>
            </x-admin.card>

            @if ($recent->isNotEmpty())
                <x-admin.card title="Your recent imports" icon="history" :padded="false">
                    <ul class="divide-y divide-slate-100 dark:divide-slate-800">
                        @foreach ($recent as $import)
                            <li>
                                <a
                                    href="{{ route('admin.transfer.imports.show', $import) }}"
                                    class="flex items-center justify-between gap-3 px-4 py-2.5 hover:bg-slate-50 dark:hover:bg-slate-800/60"
                                >
                                    <span class="min-w-0">
                                        <span class="block truncate text-sm font-medium text-slate-800 dark:text-slate-100">
                                            {{ $import->original_name }}
                                        </span>
                                        <span class="block text-xs text-slate-500 dark:text-slate-400">
                                            {{ $import->created_at->diffForHumans() }}
                                        </span>
                                    </span>
                                    <span
                                        class="{{ $import->status === 'completed' ? 'text-emerald-600 dark:text-emerald-400' : 'text-amber-600 dark:text-amber-400' }} shrink-0 text-xs font-medium"
                                    >
                                        {{ $import->status === 'completed' ? $import->tally('created') . ' imported' : 'Not imported yet' }}
                                    </span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </x-admin.card>
            @endif
        </aside>
    </div>
</x-admin>
