@php
    $managerConfig = [
        'urls' => [
            'media' => route('admin.galleries.media.index', $gallery),
            'uploads' => route('admin.galleries.uploads.store', $gallery),
            'youtube' => route('admin.galleries.media.youtube', $gallery),
            'reorder' => route('admin.galleries.media.reorder', $gallery),
            'bulkDelete' => route('admin.galleries.media.bulk-destroy', $gallery),
            'cover' => route('admin.galleries.media.cover', $gallery),
        ],
        'coverId' => $gallery->cover_item_id,
        'maxSize' => config('gallery.max_size'),
        'types' => [
            'image' => array_keys(config('gallery.mimes.image')),
            'video' => array_keys(config('gallery.mimes.video')),
        ],
    ];
    $maxImage = \Illuminate\Support\Number::fileSize(config('gallery.max_size.image'));
    $maxVideo = \Illuminate\Support\Number::fileSize(config('gallery.max_size.video'));
    $inputClasses = \App\Support\FormField::controlClasses() . ' px-3 py-2';
@endphp

<div x-data="galleryManager(@js($managerConfig))" class="space-y-6">
    <div class="grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,1fr)_22rem]">
        <x-admin.card
            title="Upload photos & videos"
            text="Large files are sent in small pieces, so a slow or dropped connection won't break the upload."
            icon="upload-cloud"
        >
            <input
                type="file"
                multiple
                class="hidden"
                x-ref="fileInput"
                accept="image/jpeg,image/png,image/webp,video/mp4,video/webm,video/quicktime,.mov,.m4v"
                x-on:change="addFiles($event.target.files)"
            />

            <button
                type="button"
                x-on:click="pick()"
                x-on:dragover.prevent="dropActive = true"
                x-on:dragleave.prevent="dropActive = false"
                x-on:drop.prevent="onDrop($event)"
                x-bind:class="
                    dropActive
                        ? 'border-blue-500 bg-blue-50/70 dark:border-blue-400 dark:bg-blue-500/10'
                        : 'border-slate-300 bg-slate-50/60 hover:border-blue-400 dark:border-slate-700 dark:bg-slate-800/40 dark:hover:border-blue-500/50'
                "
                class="flex w-full cursor-pointer flex-col items-center justify-center gap-2 rounded-xl border border-dashed px-4 py-8 text-center transition"
            >
                <span
                    class="flex h-11 w-11 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-500 shadow-xs dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300"
                >
                    <x-admin.icon name="upload-cloud" class="h-5 w-5" />
                </span>
                <span class="text-sm">
                    <span class="font-semibold text-blue-600 dark:text-blue-400">Choose files</span>
                    <span class="text-slate-600 dark:text-slate-300">or drag them here</span>
                </span>
                <span class="text-xs text-slate-500 dark:text-slate-400">
                    Images: JPG, PNG, WebP up to {{ $maxImage }} · Videos: MP4, WebM, MOV up to {{ $maxVideo }}
                </span>
                <span class="text-[11px] text-slate-400">Images are resized to at most 2000 px and saved as WebP automatically.</span>
            </button>
        </x-admin.card>

        <x-admin.card title="Add a YouTube video" text="Paste the link or the embed code." icon="youtube">
            <form x-on:submit.prevent="addYoutube()" class="space-y-3">
                <div>
                    <label for="youtube-url" class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-200">
                        YouTube link
                        <span class="text-red-500">*</span>
                    </label>
                    <input
                        id="youtube-url"
                        type="text"
                        x-model="youtube.url"
                        x-on:input="youtube.error = ''"
                        placeholder="https://youtu.be/…"
                        autocomplete="off"
                        class="{{ $inputClasses }}"
                        x-bind:aria-invalid="youtube.error ? 'true' : 'false'"
                    />
                </div>
                <div>
                    <label for="youtube-title" class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-200">Title</label>
                    <input
                        id="youtube-title"
                        type="text"
                        x-model="youtube.title"
                        placeholder="Optional: taken from YouTube if empty"
                        maxlength="255"
                        class="{{ $inputClasses }}"
                    />
                </div>
                <p x-show="youtube.error" x-cloak x-text="youtube.error" class="text-xs text-red-600 dark:text-red-400"></p>
                <x-admin.button type="submit" icon="plus" full x-bind:disabled="youtube.saving || ! youtube.url.trim()">
                    <span x-text="youtube.saving ? 'Adding…' : 'Add video'">Add video</span>
                </x-admin.button>
            </form>
        </x-admin.card>
    </div>

    <x-admin.card title="Uploads" icon="upload" x-show="queue.length" x-cloak :padded="false">
        <x-slot:actions>
            <button
                type="button"
                x-show="hasFinished"
                x-on:click="clearFinished()"
                class="cursor-pointer text-xs font-medium text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white"
            >
                Clear finished
            </button>
        </x-slot>

        <ul class="divide-y divide-slate-100 dark:divide-slate-800">
            <template x-for="task in queue" :key="task.key">
                <li class="flex items-center gap-3 px-4 py-3 sm:px-5">
                    <span
                        class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg"
                        x-bind:class="
                            task.status === 'error'
                                ? 'bg-red-50 text-red-500 dark:bg-red-500/10'
                                : task.status === 'done'
                                  ? 'bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10'
                                  : 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-300'
                        "
                    >
                        <template x-if="task.status === 'done'"><x-admin.icon name="check" class="h-4 w-4" /></template>
                        <template x-if="task.status === 'error'"><x-admin.icon name="alert-circle" class="h-4 w-4" /></template>
                        <template x-if="! ['done', 'error'].includes(task.status)">
                            <span>
                                <x-admin.icon name="film" class="h-4 w-4" x-show="task.kind === 'video'" />
                                <x-admin.icon name="image" class="h-4 w-4" x-show="task.kind !== 'video'" />
                            </span>
                        </template>
                    </span>

                    <div class="min-w-0 flex-1">
                        <div class="flex items-baseline justify-between gap-3">
                            <span class="truncate text-sm font-medium text-slate-800 dark:text-slate-100" x-text="task.name"></span>
                            <span class="tabular shrink-0 text-xs text-slate-500 dark:text-slate-400">
                                <span x-text="formatBytes(task.size)"></span>
                                ·
                                <span x-text="taskLabel(task)"></span>
                            </span>
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
                        <button
                            type="button"
                            x-show="task.status === 'uploading' || task.status === 'preparing'"
                            x-on:click="pause(task)"
                            title="Pause"
                            aria-label="Pause upload"
                            class="flex h-7 w-7 cursor-pointer items-center justify-center rounded-md text-slate-400 hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-slate-800 dark:hover:text-white"
                        >
                            <x-admin.icon name="pause" class="h-3.5 w-3.5" />
                        </button>
                        <button
                            type="button"
                            x-show="task.status === 'paused'"
                            x-on:click="resume(task)"
                            title="Resume"
                            aria-label="Resume upload"
                            class="flex h-7 w-7 cursor-pointer items-center justify-center rounded-md text-slate-400 hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-slate-800 dark:hover:text-white"
                        >
                            <x-admin.icon name="play" class="h-3.5 w-3.5" />
                        </button>
                        <button
                            type="button"
                            x-show="task.status === 'error' && task.retryable"
                            x-on:click="retry(task)"
                            title="Retry"
                            aria-label="Retry upload"
                            class="flex h-7 w-7 cursor-pointer items-center justify-center rounded-md text-slate-400 hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-slate-800 dark:hover:text-white"
                        >
                            <x-admin.icon name="refresh" class="h-3.5 w-3.5" />
                        </button>
                        <button
                            type="button"
                            x-show="task.status !== 'done' && task.status !== 'finishing'"
                            x-on:click="cancel(task)"
                            title="Remove"
                            aria-label="Cancel upload"
                            class="flex h-7 w-7 cursor-pointer items-center justify-center rounded-md text-slate-400 hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-500/10"
                        >
                            <x-admin.icon name="x" class="h-3.5 w-3.5" />
                        </button>
                    </div>
                </li>
            </template>
        </ul>
    </x-admin.card>

    <x-admin.card icon="image" :padded="false">
        <x-slot:title>
            <h3 class="text-sm font-semibold text-slate-900 dark:text-white">
                Media
                <span class="tabular ml-1 font-normal text-slate-500 dark:text-slate-400" x-text="total ? `(${total})` : ''"></span>
            </h3>
        </x-slot>

        <x-slot:actions>
            <div class="flex flex-wrap items-center gap-2" x-show="items.length" x-cloak>
                <template x-if="! selecting">
                    <span class="hidden text-xs text-slate-500 sm:inline dark:text-slate-400">Drag to reorder · Click to edit</span>
                </template>
                <template x-if="selecting">
                    <span class="flex items-center gap-2">
                        <button
                            type="button"
                            x-on:click="selectAll()"
                            class="cursor-pointer text-xs font-medium text-slate-600 hover:text-slate-900 dark:text-slate-300 dark:hover:text-white"
                            x-text="selected.length === items.length ? 'Select none' : 'Select all loaded'"
                        ></button>
                        <x-admin.button
                            type="button"
                            size="sm"
                            variant="danger"
                            icon="trash"
                            x-bind:disabled="! selected.length"
                            x-on:click="deleteSelected()"
                        >
                            <span x-text="`Delete (${selected.length})`">Delete</span>
                        </x-admin.button>
                    </span>
                </template>
                <x-admin.button type="button" size="sm" variant="secondary" x-on:click="toggleSelecting()">
                    <span x-text="selecting ? 'Done' : 'Select'">Select</span>
                </x-admin.button>
            </div>
        </x-slot>

        <div class="p-4">
            <ul class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4 2xl:grid-cols-6" x-show="items.length">
                <template x-for="item in items" :key="item.id">
                    <li
                        x-bind:draggable="! selecting"
                        x-on:dragstart="dragStart(item, $event)"
                        x-on:dragenter.prevent="dragEnter(item)"
                        x-on:dragover.prevent
                        x-on:dragend="dragEnd()"
                        x-bind:class="{
                            'opacity-40': dragId === item.id,
                            'ring-2 ring-blue-500 ring-offset-2 dark:ring-offset-slate-900':
                                selected.includes(item.id),
                        }"
                        class="group relative overflow-hidden rounded-lg bg-slate-100 dark:bg-slate-800"
                    >
                        <button
                            type="button"
                            x-on:click="open(item)"
                            class="block aspect-square w-full cursor-pointer focus:outline-none focus-visible:ring-3 focus-visible:ring-blue-500/40"
                            x-bind:aria-label="(selecting ? 'Select ' : 'Edit ') + (item.title || item.type)"
                        >
                            <template x-if="item.thumb">
                                <img
                                    x-bind:src="item.thumb"
                                    x-bind:alt="item.title || ''"
                                    loading="lazy"
                                    decoding="async"
                                    draggable="false"
                                    class="h-full w-full object-cover transition duration-300 group-hover:scale-[1.04]"
                                    x-on:error="$el.style.visibility = 'hidden'"
                                />
                            </template>
                            <template x-if="! item.thumb">
                                <span class="flex h-full w-full items-center justify-center text-slate-400 dark:text-slate-500">
                                    <x-admin.icon name="film" class="h-8 w-8" stroke="1.4" />
                                </span>
                            </template>

                            <template x-if="item.type !== 'image'">
                                <span class="pointer-events-none absolute inset-0 flex items-center justify-center">
                                    <span class="flex h-9 w-9 items-center justify-center rounded-full bg-slate-950/60 text-white backdrop-blur-sm">
                                        <x-admin.icon name="play" class="ml-0.5 h-4 w-4 fill-white" />
                                    </span>
                                </span>
                            </template>

                            <span class="pointer-events-none absolute right-1.5 bottom-1.5 flex gap-1">
                                <template x-if="item.type === 'youtube'">
                                    <span class="rounded bg-red-600 px-1.5 py-px text-[10px] font-semibold text-white">YouTube</span>
                                </template>
                                <template x-if="item.duration">
                                    <span
                                        class="tabular rounded bg-slate-950/70 px-1.5 py-px text-[10px] font-medium text-white"
                                        x-text="formatDuration(item.duration)"
                                    ></span>
                                </template>
                            </span>
                        </button>

                        <template x-if="coverId === item.id">
                            <span
                                class="pointer-events-none absolute top-1.5 left-1.5 inline-flex items-center gap-1 rounded-full bg-blue-600 px-1.5 py-px text-[10px] font-semibold text-white"
                            >
                                <x-admin.icon name="star" class="h-2.5 w-2.5 fill-white" />
                                Cover
                            </span>
                        </template>

                        <template x-if="selecting">
                            <span
                                class="pointer-events-none absolute top-1.5 right-1.5 flex h-5 w-5 items-center justify-center rounded-md border-2 border-white shadow"
                                x-bind:class="selected.includes(item.id) ? 'bg-blue-600' : 'bg-slate-950/30'"
                            >
                                <x-admin.icon name="check" class="h-3 w-3 text-white" stroke="3" x-show="selected.includes(item.id)" />
                            </span>
                        </template>

                        <span
                            class="pointer-events-none absolute inset-x-0 bottom-0 truncate bg-linear-to-t from-slate-950/70 to-transparent px-2 pt-5 pb-1.5 text-[11px] font-medium text-white opacity-0 transition group-hover:opacity-100"
                            x-text="item.title"
                            x-show="item.title && item.type === 'image'"
                        ></span>
                    </li>
                </template>
            </ul>

            <ul class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4 2xl:grid-cols-6" x-show="loading" x-cloak aria-hidden="true">
                @for ($i = 0; $i < 6; $i++)
                    <li class="aspect-square animate-pulse rounded-lg bg-slate-100 dark:bg-slate-800"></li>
                @endfor
            </ul>

            <div x-show="loadError" x-cloak class="flex items-center justify-center gap-3 py-6 text-sm text-red-600 dark:text-red-400">
                <span x-text="loadError"></span>
                <x-admin.button type="button" size="sm" variant="secondary" icon="refresh" x-on:click="loadMore()">Try again</x-admin.button>
            </div>

            <div
                x-show="! loading && ! loadError && ! items.length && ! nextUrl"
                x-cloak
                class="flex flex-col items-center justify-center px-6 py-14 text-center"
            >
                <span
                    class="mb-3 flex h-11 w-11 items-center justify-center rounded-xl border border-slate-200 bg-slate-50 text-slate-400 dark:border-slate-700 dark:bg-slate-800"
                >
                    <x-admin.icon name="image" class="h-5 w-5" />
                </span>
                <h3 class="text-sm font-semibold text-slate-900 dark:text-white">This album is empty</h3>
                <p class="mt-1 max-w-sm text-sm text-slate-500 dark:text-slate-400">Upload photos and videos above, or add a YouTube link.</p>
            </div>

            <div x-ref="sentinel" class="h-px"></div>
        </div>
    </x-admin.card>

    <template x-teleport="#admin-portal">
        <div
            x-show="editing"
            x-cloak
            x-transition.opacity
            x-on:keydown.escape.window="editing && ! confirming && close()"
            x-on:keydown.left.window="editing && ! ['INPUT', 'TEXTAREA'].includes($event.target.tagName) && step(-1)"
            x-on:keydown.right.window="editing && ! ['INPUT', 'TEXTAREA'].includes($event.target.tagName) && step(1)"
            class="fixed inset-0 z-100 flex items-center justify-center bg-slate-950/70 p-3 backdrop-blur-sm"
            role="dialog"
            aria-modal="true"
            aria-label="Edit media"
        >
            <div class="absolute inset-0" x-on:click="close()"></div>

            <template x-if="editing">
                <div
                    class="relative flex max-h-full w-full max-w-5xl flex-col overflow-hidden rounded-2xl bg-white shadow-2xl lg:flex-row dark:bg-slate-900"
                >
                    <div class="relative flex min-h-56 flex-1 items-center justify-center bg-slate-950 lg:min-h-[32rem]">
                        <template x-if="editing.type === 'image'">
                            <img
                                x-bind:src="editing.url"
                                x-bind:alt="editing.title || ''"
                                class="max-h-[70vh] w-auto max-w-full object-contain"
                            />
                        </template>
                        <template x-if="editing.type === 'video'">
                            <video
                                x-bind:src="editing.url"
                                x-bind:poster="editing.thumb || ''"
                                controls
                                playsinline
                                preload="metadata"
                                class="max-h-[70vh] w-full"
                            ></video>
                        </template>
                        <template x-if="editing.type === 'youtube'">
                            <iframe
                                x-bind:src="editing.url + '?rel=0'"
                                x-bind:title="editing.title || 'YouTube video'"
                                class="aspect-video w-full"
                                allow="accelerometer; encrypted-media; gyroscope; picture-in-picture; fullscreen"
                                allowfullscreen
                                referrerpolicy="strict-origin-when-cross-origin"
                            ></iframe>
                        </template>

                        <button
                            type="button"
                            x-on:click="step(-1)"
                            x-show="items.findIndex((i) => i.id === editing.id) > 0"
                            class="absolute top-1/2 left-2 flex h-9 w-9 -translate-y-1/2 cursor-pointer items-center justify-center rounded-full bg-white/15 text-white backdrop-blur-sm hover:bg-white/25"
                            aria-label="Previous item"
                        >
                            <x-admin.icon name="chevron-left" class="h-5 w-5" />
                        </button>
                        <button
                            type="button"
                            x-on:click="step(1)"
                            x-show="items.findIndex((i) => i.id === editing.id) < items.length - 1"
                            class="absolute top-1/2 right-2 flex h-9 w-9 -translate-y-1/2 cursor-pointer items-center justify-center rounded-full bg-white/15 text-white backdrop-blur-sm hover:bg-white/25"
                            aria-label="Next item"
                        >
                            <x-admin.icon name="chevron-right" class="h-5 w-5" />
                        </button>
                    </div>

                    <form x-on:submit.prevent="save()" class="flex w-full flex-col lg:w-80 lg:shrink-0">
                        <div class="flex items-center justify-between gap-3 border-b border-slate-100 px-4 py-3 dark:border-slate-800">
                            <span class="flex items-center gap-2 text-sm font-semibold text-slate-900 dark:text-white">
                                <x-admin.icon name="image" class="h-4 w-4 text-slate-400" x-show="editing.type === 'image'" />
                                <x-admin.icon name="film" class="h-4 w-4 text-slate-400" x-show="editing.type === 'video'" />
                                <x-admin.icon name="youtube" class="h-4 w-4 text-red-500" x-show="editing.type === 'youtube'" />
                                <span x-text="{ image: 'Photo', video: 'Video', youtube: 'YouTube video' }[editing.type]"></span>
                            </span>
                            <button
                                type="button"
                                x-on:click="close()"
                                class="cursor-pointer rounded-md p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-slate-800"
                                aria-label="Close"
                            >
                                <x-admin.icon name="x" class="h-4 w-4" />
                            </button>
                        </div>

                        <div class="flex-1 space-y-4 overflow-y-auto p-4">
                            <dl class="grid grid-cols-2 gap-x-3 gap-y-1 text-xs text-slate-500 dark:text-slate-400">
                                <template x-if="editing.width">
                                    <div>
                                        <dt class="sr-only">Size</dt>
                                        <dd class="tabular" x-text="`${editing.width} × ${editing.height} px`"></dd>
                                    </div>
                                </template>
                                <template x-if="editing.size">
                                    <div>
                                        <dt class="sr-only">File size</dt>
                                        <dd class="tabular" x-text="formatBytes(editing.size)"></dd>
                                    </div>
                                </template>
                                <template x-if="editing.duration">
                                    <div>
                                        <dt class="sr-only">Duration</dt>
                                        <dd class="tabular" x-text="formatDuration(editing.duration)"></dd>
                                    </div>
                                </template>
                                <template x-if="editing.external">
                                    <div>
                                        <dt class="sr-only">Link</dt>
                                        <dd>
                                            <a
                                                x-bind:href="editing.external"
                                                target="_blank"
                                                rel="noopener"
                                                class="inline-flex items-center gap-1 text-blue-600 hover:underline dark:text-blue-400"
                                            >
                                                Open on YouTube
                                                <x-admin.icon name="external-link" class="h-3 w-3" />
                                            </a>
                                        </dd>
                                    </div>
                                </template>
                            </dl>

                            <div>
                                <label for="media-title" class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-200">
                                    <span x-text="editing.type === 'image' ? 'Title / alt text' : 'Title'"></span>
                                </label>
                                <input id="media-title" type="text" x-model="form.title" maxlength="255" class="{{ $inputClasses }}" />
                                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400" x-show="editing.type === 'image'">
                                    Describe the photo for screen readers and image search.
                                </p>
                            </div>

                            <div>
                                <label for="media-caption" class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-200">Caption</label>
                                <textarea
                                    id="media-caption"
                                    rows="3"
                                    x-model="form.caption"
                                    maxlength="1000"
                                    placeholder="Optional: shown under the item."
                                    class="{{ $inputClasses }}"
                                ></textarea>
                            </div>

                            <template x-if="editing.type !== 'image'">
                                <div>
                                    <span class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-200">Thumbnail</span>
                                    <div class="flex items-start gap-3">
                                        <span
                                            class="flex aspect-video w-28 shrink-0 items-center justify-center overflow-hidden rounded-md bg-slate-100 text-slate-400 dark:bg-slate-800"
                                        >
                                            <template x-if="form.thumbnailPreview || (! form.removeThumbnail && editing.thumb)">
                                                <img
                                                    x-bind:src="form.thumbnailPreview || editing.thumb"
                                                    alt=""
                                                    class="h-full w-full object-cover"
                                                />
                                            </template>
                                            <template x-if="! form.thumbnailPreview && (form.removeThumbnail || ! editing.thumb)">
                                                <x-admin.icon name="film" class="h-5 w-5" />
                                            </template>
                                        </span>
                                        <div class="space-y-1.5 text-xs">
                                            <label class="block cursor-pointer font-semibold text-blue-600 hover:text-blue-700 dark:text-blue-400">
                                                Upload thumbnail
                                                <input
                                                    type="file"
                                                    accept="image/jpeg,image/png,image/webp"
                                                    class="hidden"
                                                    x-on:change="pickThumbnail($event)"
                                                />
                                            </label>
                                            <button
                                                type="button"
                                                x-show="editing.custom_thumb && ! form.removeThumbnail && ! form.thumbnail"
                                                x-on:click="form.removeThumbnail = true"
                                                class="block cursor-pointer text-slate-500 hover:text-red-600"
                                                x-text="editing.type === 'youtube' ? 'Use YouTube\'s thumbnail' : 'Remove thumbnail'"
                                            ></button>
                                            <p class="text-slate-400">JPG, PNG or WebP, up to 2 MB.</p>
                                        </div>
                                    </div>
                                </div>
                            </template>

                            <p
                                x-show="form.error"
                                x-text="form.error"
                                class="rounded-lg bg-red-50 px-3 py-2 text-xs text-red-600 dark:bg-red-500/10 dark:text-red-300"
                            ></p>

                            <div class="flex flex-wrap gap-2 border-t border-slate-100 pt-4 dark:border-slate-800">
                                <x-admin.button type="button" size="sm" variant="secondary" icon="star" x-on:click="setCover(editing)">
                                    <span x-text="coverId === editing.id ? 'Remove as cover' : 'Set as cover'">Set as cover</span>
                                </x-admin.button>
                                <x-admin.button
                                    type="button"
                                    size="sm"
                                    variant="secondary"
                                    icon="chevron-left"
                                    icon-only
                                    x-on:click="move(editing, -1)"
                                    x-bind:disabled="items.findIndex((i) => i.id === editing.id) === 0"
                                    aria-label="Move earlier"
                                    title="Move earlier"
                                />
                                <x-admin.button
                                    type="button"
                                    size="sm"
                                    variant="secondary"
                                    icon="chevron-right"
                                    icon-only
                                    x-on:click="move(editing, 1)"
                                    x-bind:disabled="items.findIndex((i) => i.id === editing.id) === items.length - 1"
                                    aria-label="Move later"
                                    title="Move later"
                                />
                            </div>
                        </div>

                        <div class="flex items-center justify-between gap-2 border-t border-slate-100 px-4 py-3 dark:border-slate-800">
                            <x-admin.button type="button" size="sm" variant="danger-outline" icon="trash" x-on:click="deleteItem(editing)">
                                Delete
                            </x-admin.button>
                            <x-admin.button type="submit" size="sm" icon="check" x-bind:disabled="form.saving">
                                <span x-text="form.saving ? 'Saving…' : 'Save'">Save</span>
                            </x-admin.button>
                        </div>
                    </form>
                </div>
            </template>
        </div>
    </template>

    <template x-teleport="#admin-portal">
        <div
            x-show="confirming"
            x-cloak
            x-transition.opacity
            x-on:keydown.escape.window="confirming && ! confirming.busy && (confirming = null)"
            class="fixed inset-0 z-110 flex items-center justify-center bg-slate-950/60 p-4 backdrop-blur-sm"
            role="alertdialog"
            aria-modal="true"
        >
            <div
                class="w-full max-w-sm rounded-2xl bg-white p-5 shadow-2xl dark:bg-slate-900"
                x-on:click.outside="confirming && ! confirming.busy && (confirming = null)"
            >
                <div class="flex gap-3">
                    <span
                        class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-red-50 text-red-600 dark:bg-red-500/10 dark:text-red-400"
                    >
                        <x-admin.icon name="trash" class="h-5 w-5" />
                    </span>
                    <div>
                        <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Are you sure?</h3>
                        <p class="mt-1 text-sm text-slate-600 dark:text-slate-300" x-text="confirming?.message"></p>
                    </div>
                </div>
                <div class="mt-5 flex justify-end gap-2">
                    <x-admin.button type="button" size="sm" variant="secondary" x-on:click="confirming = null" x-bind:disabled="confirming?.busy">
                        Cancel
                    </x-admin.button>
                    <x-admin.button type="button" size="sm" variant="danger" x-on:click="runConfirmed()" x-bind:disabled="confirming?.busy">
                        <span x-text="confirming?.busy ? 'Deleting…' : confirming?.label">Delete</span>
                    </x-admin.button>
                </div>
            </div>
        </div>
    </template>
</div>
