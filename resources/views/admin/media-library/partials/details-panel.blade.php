<template x-teleport="#admin-portal">
    <div x-show="panelOpen" x-cloak class="fixed inset-0 z-100" role="dialog" aria-modal="true" aria-label="Image details">
        <div x-show="panelOpen" x-transition.opacity class="absolute inset-0 bg-slate-950/50 backdrop-blur-[2px]" x-on:click="close()"></div>

        <aside
            x-show="panelOpen"
            x-transition:enter="transition duration-200 ease-out"
            x-transition:enter-start="translate-x-full"
            x-transition:enter-end="translate-x-0"
            x-transition:leave="transition duration-150 ease-in"
            x-transition:leave-start="translate-x-0"
            x-transition:leave-end="translate-x-full"
            x-on:dragover.prevent
            x-on:drop.prevent="onReplaceDrop($event)"
            class="absolute inset-y-0 right-0 flex w-full max-w-md flex-col bg-white shadow-2xl dark:bg-slate-900"
        >
            <header class="flex items-center justify-between gap-3 border-b border-slate-100 px-4 py-3 dark:border-slate-800">
                <h2 class="truncate text-sm font-semibold text-slate-900 dark:text-white" x-text="details?.filename ?? 'Loading…'"></h2>
                <button
                    type="button"
                    x-on:click="close()"
                    x-bind:disabled="replace?.status === 'uploading'"
                    class="cursor-pointer rounded-md p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-700 disabled:opacity-40 dark:hover:bg-slate-800"
                    aria-label="Close"
                >
                    <x-admin.icon name="x" class="h-4 w-4" />
                </button>
            </header>

            <div class="flex-1 overflow-y-auto">
                <div x-show="detailsLoading && ! details" class="space-y-3 p-4">
                    <div class="aspect-video animate-pulse rounded-xl bg-slate-100 dark:bg-slate-800"></div>
                    <div class="h-4 w-2/3 animate-pulse rounded bg-slate-100 dark:bg-slate-800"></div>
                    <div class="h-4 w-1/2 animate-pulse rounded bg-slate-100 dark:bg-slate-800"></div>
                </div>

                <div
                    x-show="detailsError"
                    class="m-4 flex items-center justify-between gap-3 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700 dark:bg-red-500/10 dark:text-red-300"
                >
                    <span x-text="detailsError"></span>
                    <x-admin.button type="button" size="sm" variant="secondary" x-on:click="loadDetails()">Retry</x-admin.button>
                </div>

                <template x-if="details">
                    <div class="space-y-5 p-4" x-bind:class="detailsLoading && 'opacity-60'">
                        <a
                            x-bind:href="details.url"
                            target="_blank"
                            rel="noopener"
                            class="media-checker block overflow-hidden rounded-xl border border-slate-200 dark:border-slate-800"
                        >
                            <img
                                x-bind:src="details.url"
                                x-bind:alt="details.filename"
                                decoding="async"
                                class="mx-auto max-h-72 w-auto object-contain"
                            />
                        </a>

                        <div class="space-y-2">
                            <div class="flex items-center gap-2">
                                <code
                                    class="min-w-0 flex-1 truncate rounded-md bg-slate-100 px-2 py-1 text-[11px] text-slate-700 dark:bg-slate-800 dark:text-slate-300"
                                    x-text="details.display_path"
                                    x-bind:title="details.display_path"
                                ></code>
                                <x-admin.button type="button" size="sm" variant="secondary" icon="copy" x-on:click="copyUrl()">
                                    Copy link
                                </x-admin.button>
                            </div>

                            <dl class="grid grid-cols-2 gap-x-4 gap-y-2 rounded-lg border border-slate-100 p-3 text-xs dark:border-slate-800">
                                <div>
                                    <dt class="text-slate-400">Dimensions</dt>
                                    <dd
                                        class="tabular font-medium text-slate-800 dark:text-slate-100"
                                        x-text="details.dimensions ? details.dimensions + ' px' : '—'"
                                    ></dd>
                                </div>
                                <div>
                                    <dt class="text-slate-400">File size</dt>
                                    <dd
                                        class="tabular font-medium"
                                        x-bind:class="
                                            details.is_large
                                                ? 'text-amber-600 dark:text-amber-400'
                                                : 'text-slate-800 dark:text-slate-100'
                                        "
                                        x-text="details.size_label"
                                    ></dd>
                                </div>
                                <div>
                                    <dt class="text-slate-400">Type</dt>
                                    <dd class="font-medium text-slate-800 uppercase dark:text-slate-100" x-text="details.extension"></dd>
                                </div>
                                <div>
                                    <dt class="text-slate-400">Last changed</dt>
                                    <dd
                                        class="font-medium text-slate-800 dark:text-slate-100"
                                        x-text="details.modified"
                                        x-bind:title="details.modified_full"
                                    ></dd>
                                </div>
                            </dl>

                            <p
                                x-show="details.is_large"
                                class="flex gap-2 rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-800 dark:bg-amber-500/10 dark:text-amber-300"
                            >
                                <x-admin.icon name="alert-triangle" class="mt-px h-3.5 w-3.5 shrink-0" />
                                Large images slow pages down. Replacing it with a smaller or WebP version helps.
                            </p>
                            <p
                                x-show="details.location === 'public'"
                                class="flex gap-2 rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-800 dark:bg-amber-500/10 dark:text-amber-300"
                            >
                                <x-admin.icon name="info" class="mt-px h-3.5 w-3.5 shrink-0" />
                                This file is in the public folder, which is part of the website's code: deploying a new version of the site restores
                                the original.
                            </p>
                        </div>

                        <section>
                            <h3 class="mb-2 flex items-center gap-2 text-xs font-semibold tracking-wide text-slate-500 uppercase dark:text-slate-400">
                                Used in
                                <span
                                    class="tabular rounded-full bg-slate-100 px-1.5 text-[11px] text-slate-600 dark:bg-slate-800 dark:text-slate-300"
                                    x-text="details.usage_count"
                                ></span>
                            </h3>
                            <p x-show="! details.usages.length" class="text-sm text-slate-500 dark:text-slate-400">
                                Not used anywhere on the site that we can see.
                            </p>
                            <ul
                                x-show="details.usages.length"
                                class="divide-y divide-slate-100 rounded-lg border border-slate-100 dark:divide-slate-800 dark:border-slate-800"
                            >
                                <template x-for="(usage, index) in details.usages" :key="index">
                                    <li class="flex items-center justify-between gap-3 px-3 py-2 text-sm">
                                        <span class="min-w-0">
                                            <span
                                                class="block truncate font-medium text-slate-800 dark:text-slate-100"
                                                x-text="usage.title || usage.source"
                                            ></span>
                                            <span
                                                class="block truncate text-xs text-slate-500 dark:text-slate-400"
                                                x-text="[usage.source, usage.detail].filter(Boolean).join(' · ')"
                                            ></span>
                                        </span>
                                        <template x-if="usage.url">
                                            <a
                                                x-bind:href="usage.url"
                                                class="shrink-0 text-xs font-medium text-blue-600 hover:underline dark:text-blue-400"
                                            >
                                                Open
                                            </a>
                                        </template>
                                    </li>
                                </template>
                            </ul>
                        </section>

                        <section x-show="details.can_replace">
                            <h3 class="mb-2 text-xs font-semibold tracking-wide text-slate-500 uppercase dark:text-slate-400">Replace image</h3>
                            <input
                                type="file"
                                class="hidden"
                                id="media-replace-input"
                                x-bind:accept="
                                    details.accept.join(',') +
                                        (details.extension === 'ico'
                                            ? ',.ico'
                                            : details.extension === 'svg'
                                              ? ',.svg'
                                              : '')
                                "
                                x-on:change="chooseReplacement($event.target.files[0])"
                            />

                            <template x-if="! replace">
                                <button
                                    type="button"
                                    x-on:click="pickReplacement()"
                                    class="flex w-full cursor-pointer flex-col items-center gap-1 rounded-xl border border-dashed border-slate-300 bg-slate-50/60 px-4 py-5 text-center transition hover:border-blue-400 dark:border-slate-700 dark:bg-slate-800/40"
                                >
                                    <x-admin.icon name="refresh" class="h-5 w-5 text-slate-400" />
                                    <span class="text-sm font-semibold text-blue-600 dark:text-blue-400">Choose a new image</span>
                                    <span class="text-xs text-slate-500 dark:text-slate-400">
                                        or drop it on this panel. It keeps the name
                                        <span class="font-mono" x-text="details.filename"></span>
                                        , so every page using it updates.
                                    </span>
                                </button>
                            </template>

                            <template x-if="replace">
                                <div class="space-y-3 rounded-xl border border-slate-200 p-3 dark:border-slate-700">
                                    <div class="flex items-center justify-between gap-3 text-sm">
                                        <span class="truncate font-medium text-slate-800 dark:text-slate-100" x-text="replace.file.name"></span>
                                        <span class="tabular shrink-0 text-xs text-slate-500" x-text="bytes(replace.file.size)"></span>
                                    </div>

                                    <template x-if="replace.status === 'choose'">
                                        <div class="space-y-2">
                                            <p class="flex gap-2 text-xs text-amber-700 dark:text-amber-300">
                                                <x-admin.icon name="alert-triangle" class="mt-px h-3.5 w-3.5 shrink-0" />
                                                <span>
                                                    This image is
                                                    <strong x-text="`${replace.choice.width} × ${replace.choice.height}`"></strong>
                                                    , a different shape from the current
                                                    <strong x-text="details.dimensions"></strong>
                                                    . Pages may crop or stretch it.
                                                </span>
                                            </p>
                                            <div class="grid gap-2">
                                                <button
                                                    type="button"
                                                    x-on:click="startReplace('cover')"
                                                    class="cursor-pointer rounded-lg border border-blue-200 bg-blue-50 px-3 py-2 text-left text-sm hover:border-blue-400 dark:border-blue-500/30 dark:bg-blue-500/10"
                                                >
                                                    <span class="block font-semibold text-blue-700 dark:text-blue-300">
                                                        Fit to the current size (recommended)
                                                    </span>
                                                    <span
                                                        class="block text-xs text-slate-600 dark:text-slate-300"
                                                        x-text="`Scaled and centre-cropped to exactly ${details.dimensions} px.`"
                                                    ></span>
                                                </button>
                                                <button
                                                    type="button"
                                                    x-on:click="startReplace('keep')"
                                                    class="cursor-pointer rounded-lg border border-slate-200 px-3 py-2 text-left text-sm hover:border-slate-300 dark:border-slate-700"
                                                >
                                                    <span class="block font-semibold text-slate-800 dark:text-slate-100">Keep my image's shape</span>
                                                    <span class="block text-xs text-slate-500 dark:text-slate-400">
                                                        Used as it is (large photos are still scaled down).
                                                    </span>
                                                </button>
                                            </div>
                                        </div>
                                    </template>

                                    <template x-if="replace.status === 'uploading' || replace.status === 'checking'">
                                        <div>
                                            <div class="h-1.5 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800">
                                                <div
                                                    class="h-full rounded-full bg-blue-600 transition-[width] duration-300 dark:bg-blue-500"
                                                    x-bind:style="`width: ${replace.progress}%`"
                                                ></div>
                                            </div>
                                            <p
                                                class="tabular mt-1 text-xs text-slate-500"
                                                x-text="
                                                    replace.status === 'checking'
                                                        ? 'Checking…'
                                                        : replace.progress >= 99
                                                          ? 'Saving…'
                                                          : `Uploading… ${replace.progress}%`
                                                "
                                            ></p>
                                        </div>
                                    </template>

                                    <template x-if="replace.status === 'error'">
                                        <p
                                            class="rounded-lg bg-red-50 px-3 py-2 text-xs text-red-600 dark:bg-red-500/10 dark:text-red-300"
                                            x-text="replace.error"
                                        ></p>
                                    </template>

                                    <div class="flex justify-end gap-2">
                                        <x-admin.button
                                            type="button"
                                            size="sm"
                                            variant="secondary"
                                            icon="refresh"
                                            x-show="replace.status === 'error' && replace.retryable"
                                            x-on:click="startReplace(replace.fit ?? 'keep')"
                                        >
                                            Try again
                                        </x-admin.button>
                                        <x-admin.button
                                            type="button"
                                            size="sm"
                                            variant="ghost"
                                            x-show="replace.status !== 'uploading' || replace.progress < 99"
                                            x-on:click="cancelReplace()"
                                        >
                                            Cancel
                                        </x-admin.button>
                                    </div>
                                </div>
                            </template>
                        </section>

                        <section x-show="details.versions.length">
                            <h3 class="mb-2 text-xs font-semibold tracking-wide text-slate-500 uppercase dark:text-slate-400">Earlier versions</h3>
                            <ul class="space-y-2">
                                <template x-for="version in details.versions" :key="version.id">
                                    <li class="flex items-center gap-3 rounded-lg border border-slate-100 p-2 dark:border-slate-800">
                                        <span class="media-checker h-12 w-12 shrink-0 overflow-hidden rounded-md">
                                            <img
                                                x-bind:src="version.preview"
                                                alt=""
                                                loading="lazy"
                                                decoding="async"
                                                class="h-full w-full object-contain"
                                            />
                                        </span>
                                        <span class="min-w-0 flex-1 text-xs">
                                            <span
                                                class="block font-medium text-slate-800 dark:text-slate-100"
                                                x-text="version.created"
                                                x-bind:title="version.created_full"
                                            ></span>
                                            <span
                                                class="tabular block truncate text-slate-500 dark:text-slate-400"
                                                x-text="
                                                    [version.dimensions, version.size_label, version.by && 'by ' + version.by]
                                                        .filter(Boolean)
                                                        .join(' · ')
                                                "
                                            ></span>
                                        </span>
                                        <x-admin.button
                                            type="button"
                                            size="sm"
                                            variant="secondary"
                                            x-show="details.can_replace"
                                            x-on:click="restore(version)"
                                        >
                                            Restore
                                        </x-admin.button>
                                    </li>
                                </template>
                            </ul>
                        </section>
                    </div>
                </template>
            </div>

            <footer x-show="details" class="flex items-center justify-between gap-2 border-t border-slate-100 px-4 py-3 dark:border-slate-800">
                <template x-if="details">
                    <span class="text-xs text-slate-500 dark:text-slate-400">
                        <template x-if="details.can_delete">
                            <x-admin.button type="button" size="sm" variant="danger-outline" icon="trash" x-on:click="remove()">
                                Delete
                            </x-admin.button>
                        </template>
                        <template x-if="! details.can_delete && details.location === 'public'">
                            <span>Public-folder files can be replaced, not deleted.</span>
                        </template>
                        <template x-if="! details.can_delete && details.location === 'storage' && details.usage_count">
                            <span>In use, so it can't be deleted.</span>
                        </template>
                    </span>
                </template>
                <template x-if="details">
                    <a
                        x-bind:href="details.url"
                        x-bind:download="details.filename"
                        class="inline-flex items-center gap-1.5 text-xs font-medium text-slate-600 hover:text-slate-900 dark:text-slate-300 dark:hover:text-white"
                    >
                        <x-admin.icon name="download" class="h-3.5 w-3.5" />
                        Download
                    </a>
                </template>
            </footer>
        </aside>

        <div
            x-show="confirming"
            x-cloak
            x-transition.opacity
            class="absolute inset-0 z-10 flex items-center justify-center bg-slate-950/40 p-4"
            role="alertdialog"
            aria-modal="true"
        >
            <div class="w-full max-w-sm rounded-2xl bg-white p-5 shadow-2xl dark:bg-slate-900" x-on:click.outside="! busy && (confirming = null)">
                <h3 class="text-sm font-semibold text-slate-900 dark:text-white" x-text="confirming?.title"></h3>
                <p class="mt-1 text-sm text-slate-600 dark:text-slate-300" x-text="confirming?.message"></p>
                <div class="mt-5 flex justify-end gap-2">
                    <x-admin.button type="button" size="sm" variant="secondary" x-on:click="confirming = null" x-bind:disabled="busy">
                        Cancel
                    </x-admin.button>
                    <x-admin.button
                        type="button"
                        size="sm"
                        x-bind:class="confirming?.danger && '!bg-red-600 hover:!bg-red-700'"
                        x-on:click="runConfirmed()"
                        x-bind:disabled="busy"
                    >
                        <span x-text="busy ? 'Working…' : confirming?.label">OK</span>
                    </x-admin.button>
                </div>
            </div>
        </div>
    </div>
</template>
