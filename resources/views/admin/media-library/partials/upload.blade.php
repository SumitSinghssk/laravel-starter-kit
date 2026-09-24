<div x-show="uploadOpen || queue.length" x-cloak x-transition class="mb-5">
    <x-admin.card
        title="Upload images"
        text="Files are sent in small pieces, so a slow or dropped connection won't break the upload."
        icon="upload-cloud"
    >
        <x-slot:actions>
            <button
                type="button"
                x-show="! activeUploads"
                x-on:click="
                    uploadOpen = false
                    queue = queue.filter((t) => t.status === 'paused')
                "
                class="cursor-pointer rounded-md p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-slate-800"
                aria-label="Close"
            >
                <x-admin.icon name="x" class="h-4 w-4" />
            </button>
        </x-slot>

        <div class="grid grid-cols-1 gap-4 lg:grid-cols-[16rem_minmax(0,1fr)]">
            <div>
                <label for="media-folder" class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-200">Folder</label>
                <div class="flex items-center gap-1.5">
                    <span class="shrink-0 font-mono text-xs text-slate-400">storage/</span>
                    <input
                        id="media-folder"
                        type="text"
                        x-model="folder"
                        list="media-folders"
                        maxlength="200"
                        placeholder="{{ config('media.upload_folder') }}"
                        class="{{ \App\Support\FormField::controlClasses() }} px-3 py-2 font-mono text-xs"
                    />
                </div>
                <datalist id="media-folders">
                    @foreach (array_keys($folders) as $option)
                        @if (str_starts_with($option, 'storage:') && $option !== 'storage:')
                            <option value="{{ substr($option, 8) }}"></option>
                        @endif
                    @endforeach
                </datalist>
                <p class="mt-1.5 text-xs text-slate-500 dark:text-slate-400">
                    Letters, numbers and dashes; use / for sub-folders. A file with the same name gets “-2” added instead of overwriting.
                </p>
            </div>

            <div>
                <input
                    type="file"
                    multiple
                    class="hidden"
                    x-ref="uploadInput"
                    accept="image/jpeg,image/png,image/webp,image/gif,image/svg+xml,.svg,.ico,image/x-icon"
                    x-on:change="addFiles($event.target.files)"
                />
                <button
                    type="button"
                    x-on:click="pickUploads()"
                    x-on:dragover.prevent="dropActive = true"
                    x-on:dragleave.prevent="dropActive = false"
                    x-on:drop.prevent="onDrop($event)"
                    x-bind:class="
                        dropActive
                            ? 'border-blue-500 bg-blue-50/70 dark:border-blue-400 dark:bg-blue-500/10'
                            : 'border-slate-300 bg-slate-50/60 hover:border-blue-400 dark:border-slate-700 dark:bg-slate-800/40 dark:hover:border-blue-500/50'
                    "
                    class="flex h-full min-h-32 w-full cursor-pointer flex-col items-center justify-center gap-1.5 rounded-xl border border-dashed px-4 py-6 text-center transition"
                >
                    <x-admin.icon name="upload-cloud" class="h-6 w-6 text-slate-400" />
                    <span class="text-sm">
                        <span class="font-semibold text-blue-600 dark:text-blue-400">Choose images</span>
                        <span class="text-slate-600 dark:text-slate-300">or drag them here</span>
                    </span>
                    <span class="text-xs text-slate-500 dark:text-slate-400">
                        JPG, PNG, WebP, GIF, SVG or ICO · up to {{ \Illuminate\Support\Number::fileSize(config('media.max_size')) }} each · large
                        photos are scaled to {{ config('media.max_dimension') }} px
                    </span>
                </button>
            </div>
        </div>

        <ul
            class="mt-4 divide-y divide-slate-100 rounded-lg border border-slate-100 dark:divide-slate-800 dark:border-slate-800"
            x-show="queue.length"
        >
            <template x-for="task in queue" :key="task.key">
                <li class="flex items-center gap-3 px-3 py-2.5">
                    <div class="min-w-0 flex-1">
                        <div class="flex items-baseline justify-between gap-3">
                            <span class="truncate text-sm font-medium text-slate-800 dark:text-slate-100" x-text="task.name"></span>
                            <span
                                class="tabular shrink-0 text-xs text-slate-500 dark:text-slate-400"
                                x-text="bytes(task.size) + ' · ' + taskLabel(task)"
                            ></span>
                        </div>
                        <div class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800" x-show="task.status !== 'error'">
                            <div
                                class="h-full rounded-full transition-[width] duration-300"
                                x-bind:class="
                                    task.status === 'done'
                                        ? 'bg-emerald-500'
                                        : task.status === 'paused'
                                          ? 'bg-amber-400'
                                          : 'bg-blue-600 dark:bg-blue-500'
                                "
                                x-bind:style="`width: ${task.progress}%`"
                            ></div>
                        </div>
                        <p x-show="task.status === 'error'" x-text="task.error" class="mt-1 text-xs text-red-600 dark:text-red-400"></p>
                    </div>

                    <div class="flex shrink-0 items-center gap-1">
                        <x-admin.button
                            type="button"
                            variant="ghost"
                            size="sm"
                            icon-only
                            icon="pause"
                            x-show="task.status === 'uploading'"
                            x-on:click="pause(task)"
                            aria-label="Pause upload"
                            title="Pause"
                        />
                        <x-admin.button
                            type="button"
                            variant="ghost"
                            size="sm"
                            icon-only
                            icon="play"
                            x-show="task.status === 'paused'"
                            x-on:click="resume(task)"
                            aria-label="Resume upload"
                            title="Resume"
                        />
                        <x-admin.button
                            type="button"
                            variant="ghost"
                            size="sm"
                            icon-only
                            icon="refresh"
                            x-show="task.status === 'error' && task.retryable"
                            x-on:click="resume(task)"
                            aria-label="Retry upload"
                            title="Retry"
                        />
                        <x-admin.button
                            type="button"
                            variant="ghost"
                            size="sm"
                            icon-only
                            icon="x"
                            x-show="task.status !== 'done'"
                            x-on:click="cancel(task)"
                            aria-label="Remove from the list"
                            title="Remove"
                        />
                    </div>
                </li>
            </template>
        </ul>

        <div x-show="settled && uploadedCount" x-cloak class="mt-3 flex items-center justify-between gap-3 text-sm">
            <span
                class="text-emerald-700 dark:text-emerald-400"
                x-text="`${uploadedCount} image${uploadedCount === 1 ? '' : 's'} uploaded.`"
            ></span>
            <x-admin.button type="button" size="sm" variant="secondary" x-on:click="showNewest()">Show in library</x-admin.button>
        </div>
    </x-admin.card>
</div>
