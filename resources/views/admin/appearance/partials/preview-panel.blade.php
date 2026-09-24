<aside class="2xl:sticky 2xl:top-4">
    <div class="overflow-hidden rounded-xl border border-slate-200/80 bg-white shadow-xs dark:border-slate-800 dark:bg-slate-900">
        <div class="flex items-center justify-between gap-3 border-b border-slate-100 px-3 py-2 dark:border-slate-800">
            <p class="flex items-center gap-2 text-sm font-semibold text-slate-800 dark:text-slate-100">
                <span class="relative flex h-2 w-2">
                    <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-60" x-show="checking"></span>
                    <span
                        class="relative inline-flex h-2 w-2 rounded-full"
                        x-bind:class="checking ? 'bg-emerald-500' : 'bg-slate-300 dark:bg-slate-600'"
                    ></span>
                </span>
                Live preview
            </p>

            <div class="flex items-center gap-1">
                <div class="flex rounded-lg bg-slate-100 p-0.5 dark:bg-slate-800">
                    <button
                        type="button"
                        x-on:click="device = 'desktop'"
                        x-bind:class="
                            device === 'desktop'
                                ? 'bg-white text-slate-900 shadow-xs dark:bg-slate-900 dark:text-white'
                                : 'text-slate-500'
                        "
                        class="flex h-7 w-8 cursor-pointer items-center justify-center rounded-md"
                        aria-label="Desktop width"
                        title="Desktop"
                    >
                        <x-admin.icon name="monitor" class="h-4 w-4" />
                    </button>
                    <button
                        type="button"
                        x-on:click="device = 'mobile'"
                        x-bind:class="
                            device === 'mobile'
                                ? 'bg-white text-slate-900 shadow-xs dark:bg-slate-900 dark:text-white'
                                : 'text-slate-500'
                        "
                        class="flex h-7 w-8 cursor-pointer items-center justify-center rounded-md"
                        aria-label="Phone width"
                        title="Phone"
                    >
                        <x-admin.icon name="smartphone" class="h-4 w-4" />
                    </button>
                </div>
                <button
                    type="button"
                    x-show="values.style.dark_mode === 'auto'"
                    x-cloak
                    x-on:click="previewDark = ! previewDark"
                    x-bind:class="
                        previewDark
                            ? 'bg-slate-900 text-white dark:bg-white dark:text-slate-900'
                            : 'bg-slate-100 text-slate-500 dark:bg-slate-800'
                    "
                    class="flex h-8 w-8 cursor-pointer items-center justify-center rounded-lg"
                    x-bind:aria-pressed="previewDark.toString()"
                    x-bind:title="previewDark ? 'Showing dark mode' : 'Show dark mode'"
                >
                    <x-admin.icon name="moon" class="h-4 w-4" x-show="! previewDark" />
                    <x-admin.icon name="sun" class="h-4 w-4" x-show="previewDark" />
                </button>
                <a
                    href="{{ route('admin.appearance.preview') }}"
                    target="_blank"
                    rel="noopener"
                    class="flex h-8 w-8 items-center justify-center rounded-lg text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800"
                    title="Open the saved look in a new tab"
                >
                    <x-admin.icon name="external-link" class="h-4 w-4" />
                </a>
            </div>
        </div>

        <div class="media-checker flex justify-center overflow-hidden">
            <iframe
                x-ref="frame"
                src="{{ route('admin.appearance.preview') }}"
                x-on:load="frameLoaded()"
                title="Website preview"
                loading="lazy"
                class="h-[70vh] min-h-[32rem] border-0 bg-white transition-[width] duration-300"
                x-bind:class="
                    device === 'mobile'
                        ? 'w-[390px] border-x border-slate-200 dark:border-slate-700'
                        : 'w-full'
                "
            ></iframe>
        </div>
    </div>
    <p class="mt-2 px-1 text-xs text-slate-500 dark:text-slate-400">A sample page in your website's style. Links in it don't go anywhere.</p>
</aside>
