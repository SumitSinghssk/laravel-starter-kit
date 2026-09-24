<x-admin.card
    title="Colour variables"
    text="Name your colours once, then choose where each one goes. Turn on shades to get 11 lighter and darker versions."
    icon="palette"
    :padded="false"
>
    @if ($canEdit)
        <x-slot:actions>
            <x-admin.button type="button" size="sm" icon="plus" x-on:click="addVariable()">Add colour</x-admin.button>
        </x-slot>
    @endif

    <p
        class="border-b border-slate-100 px-4 py-2.5 text-xs text-red-600 dark:border-slate-800 dark:text-red-400"
        x-show="error('variables')"
        x-cloak
        x-text="error('variables')"
    ></p>

    <ul class="divide-y divide-slate-100 dark:divide-slate-800">
        <template x-for="(variable, index) in values.variables" :key="variable._key">
            <li class="px-4 py-4 sm:px-5" x-data="{ open: false }">
                <div class="flex flex-wrap items-start gap-3 sm:flex-nowrap">
                    <label
                        class="media-checker relative mt-0.5 flex h-10 w-10 shrink-0 cursor-pointer overflow-hidden rounded-lg ring-1 ring-slate-200 ring-inset dark:ring-slate-700"
                        x-bind:title="canEdit ? 'Pick a colour' : ''"
                    >
                        <span class="absolute inset-0" x-bind:style="validColor(variable.value) ? 'background:' + variable.value : ''"></span>
                        <input
                            type="color"
                            class="absolute inset-0 h-full w-full cursor-pointer opacity-0"
                            x-bind:value="pickerHex(variable)"
                            x-on:input="variable.value = $event.target.value"
                            x-bind:disabled="! canEdit"
                            aria-label="Pick a colour"
                        />
                    </label>

                    <div class="grid min-w-0 flex-1 grid-cols-1 gap-3 sm:grid-cols-2">
                        <div>
                            <label class="sr-only" x-bind:for="'var-name-' + variable._key">Name</label>
                            <div
                                class="flex rounded-lg border bg-white shadow-xs focus-within:ring-3 focus-within:ring-blue-500/20 dark:bg-slate-900"
                                x-bind:class="
                                    error('variables.' + index + '.name')
                                        ? 'border-red-400 dark:border-red-500/60'
                                        : 'border-slate-200 focus-within:border-blue-400 dark:border-slate-700'
                                "
                            >
                                <span
                                    class="flex shrink-0 items-center border-r border-slate-100 pr-2 pl-3 font-mono text-xs whitespace-nowrap text-slate-400 dark:border-slate-800"
                                >
                                    --theme-
                                </span>
                                <input
                                    type="text"
                                    data-variable-name
                                    x-bind:id="'var-name-' + variable._key"
                                    x-model="variable.name"
                                    x-on:change="renamed(variable)"
                                    x-bind:readonly="! canEdit"
                                    maxlength="40"
                                    spellcheck="false"
                                    autocomplete="off"
                                    placeholder="footer-bg"
                                    class="h-9 w-full min-w-0 border-0 bg-transparent px-2.5 font-mono text-sm text-slate-900 outline-none focus:ring-0 dark:text-white"
                                />
                            </div>
                            <p
                                class="mt-1 text-xs text-red-600 dark:text-red-400"
                                x-show="error('variables.' + index + '.name')"
                                x-text="error('variables.' + index + '.name')"
                            ></p>
                        </div>

                        <div>
                            <label class="sr-only" x-bind:for="'var-value-' + variable._key">Value</label>
                            <input
                                type="text"
                                x-bind:id="'var-value-' + variable._key"
                                x-model.debounce.300ms="variable.value"
                                x-bind:readonly="! canEdit"
                                maxlength="80"
                                spellcheck="false"
                                autocomplete="off"
                                placeholder="#4f46e5 or rgb(79 70 229)"
                                x-bind:class="
                                    error('variables.' + index + '.value')
                                        ? 'border-red-400 dark:border-red-500/60'
                                        : 'border-slate-200 focus:border-blue-400 dark:border-slate-700'
                                "
                                class="h-9 w-full rounded-lg border bg-white px-3 font-mono text-sm text-slate-900 shadow-xs outline-none focus:ring-3 focus:ring-blue-500/20 dark:bg-slate-900 dark:text-white"
                            />
                            <p
                                class="mt-1 text-xs text-red-600 dark:text-red-400"
                                x-show="error('variables.' + index + '.value')"
                                x-text="error('variables.' + index + '.value')"
                            ></p>
                        </div>
                    </div>

                    <div class="flex shrink-0 items-center gap-1 pt-1.5">
                        <label
                            class="flex cursor-pointer items-center gap-2 rounded-md px-2 py-1 text-xs font-medium text-slate-600 hover:bg-slate-50 dark:text-slate-300 dark:hover:bg-slate-800"
                            title="Make 50 … 950 shades of this colour"
                        >
                            <input
                                type="checkbox"
                                x-model="variable.shades"
                                x-bind:disabled="! canEdit"
                                class="h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500/30 dark:border-slate-600 dark:bg-slate-800"
                            />
                            Shades
                        </label>
                        <button
                            type="button"
                            x-on:click="open = ! open"
                            class="flex h-8 w-8 cursor-pointer items-center justify-center rounded-md text-slate-400 hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-slate-800 dark:hover:text-white"
                            x-bind:aria-expanded="open.toString()"
                            title="How to use this colour"
                        >
                            <x-admin.icon name="code" class="h-4 w-4" />
                        </button>
                        <template x-if="canEdit">
                            <button
                                type="button"
                                x-on:click="removeVariable(index)"
                                x-bind:disabled="usedBy(variable.name).length > 0 || values.variables.length === 1"
                                x-bind:title="
                                    usedBy(variable.name).length
                                        ? 'In use: ' +
                                          usedBy(variable.name).join(', ') +
                                          '. Choose other colours there first.'
                                        : 'Delete colour'
                                "
                                class="flex h-8 w-8 cursor-pointer items-center justify-center rounded-md text-slate-400 hover:bg-red-50 hover:text-red-600 disabled:cursor-not-allowed disabled:opacity-40 disabled:hover:bg-transparent disabled:hover:text-slate-400 dark:hover:bg-red-500/10"
                            >
                                <x-admin.icon name="trash" class="h-4 w-4" />
                            </button>
                        </template>
                    </div>
                </div>

                <div class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 pl-13 text-xs text-slate-500 dark:text-slate-400">
                    <span
                        x-show="usedBy(variable.name).length"
                        x-text="
                            'Used for: ' +
                                usedBy(variable.name).slice(0, 4).join(', ') +
                                (usedBy(variable.name).length > 4
                                    ? ' and ' + (usedBy(variable.name).length - 4) + ' more'
                                    : '')
                        "
                    ></span>
                    <span x-show="! usedBy(variable.name).length">
                        Not used yet. Pick it under “Where colours go”, or use it in your own HTML/CSS.
                    </span>
                </div>

                <template x-if="variable.shades && shadesOf(variable.name).length">
                    <div class="mt-3 pl-13">
                        <div class="flex overflow-hidden rounded-lg ring-1 ring-slate-200 dark:ring-slate-700">
                            <template x-for="shade in shadesOf(variable.name)" :key="shade">
                                <button
                                    type="button"
                                    x-on:click="copy(shade)"
                                    x-bind:style="'background:' + palette[shade]"
                                    x-bind:title="shade + ' · click to copy the name'"
                                    class="group relative h-9 flex-1 cursor-pointer"
                                >
                                    <span
                                        class="absolute inset-x-0 bottom-0.5 hidden text-center font-mono text-[9px] font-semibold text-white mix-blend-difference group-hover:block sm:block"
                                        x-text="shade.split('-').pop()"
                                    ></span>
                                </button>
                            </template>
                        </div>
                    </div>
                </template>

                <div x-show="open" x-cloak x-transition.opacity class="mt-3 ml-13 rounded-lg bg-slate-50 p-3 text-xs dark:bg-slate-800/60">
                    <p class="mb-2 text-slate-500 dark:text-slate-400">Use it in page content, the custom CSS box or code:</p>
                    <div class="flex flex-wrap gap-1.5">
                        <template
                            x-for="
                                snippet in
                                    [
                                        'var(--theme-' + variable.name + ')',
                                        'bg-theme-' + variable.name,
                                        'text-theme-' + variable.name,
                                        'border-theme-' + variable.name,
                                        'hover:bg-theme-' + variable.name,
                                    ]
                            "
                            :key="snippet"
                        >
                            <button
                                type="button"
                                x-on:click="copy(snippet)"
                                class="inline-flex cursor-pointer items-center gap-1.5 rounded-md border border-slate-200 bg-white px-2 py-1 font-mono text-slate-700 hover:border-blue-300 hover:text-blue-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"
                            >
                                <span x-text="snippet"></span>
                                <x-admin.icon name="copy" class="h-3 w-3 opacity-60" />
                            </button>
                        </template>
                    </div>
                </div>
            </li>
        </template>
    </ul>
</x-admin.card>

<p class="mt-3 flex items-start gap-2 px-1 text-xs text-slate-500 dark:text-slate-400">
    <x-admin.icon name="info" class="mt-px h-3.5 w-3.5 shrink-0" />
    <span>
        Values can be #hex, rgb(), hsl() or oklch(). Renaming a colour updates every place it is used on the site; HTML you wrote with the old class
        name keeps the old name.
    </span>
</p>
