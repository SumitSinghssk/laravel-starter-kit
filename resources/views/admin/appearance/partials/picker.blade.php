<template x-teleport="#admin-portal">
    <div
        x-ref="picker"
        x-show="picker.open"
        x-cloak
        x-transition.opacity.duration.100ms
        x-bind:style="picker.style"
        class="admin-theme z-[120] max-h-[26rem] overflow-y-auto rounded-xl border border-slate-200 bg-white p-2 shadow-xl dark:border-slate-700 dark:bg-slate-900"
        role="dialog"
        aria-label="Choose a colour"
    >
        <p class="px-2 pt-1 pb-2 text-xs font-medium text-slate-500 dark:text-slate-400">
            <span x-text="picker.slot ? slots[picker.slot].label : ''"></span>
            <span x-show="picker.mode === 'dark'">· dark mode</span>
        </p>

        <template x-if="picker.mode === 'dark'">
            <button
                type="button"
                x-on:click="choose(null)"
                class="mb-1 flex w-full cursor-pointer items-center gap-2.5 rounded-lg px-2 py-1.5 text-left text-sm hover:bg-slate-50 dark:hover:bg-slate-800"
                x-bind:class="! slotValue(picker.slot, 'dark') && 'bg-blue-50 dark:bg-blue-500/10'"
            >
                <span
                    class="flex h-6 w-6 items-center justify-center rounded-md border border-dashed border-slate-300 text-slate-400 dark:border-slate-600"
                >
                    <x-admin.icon name="minus" class="h-3.5 w-3.5" />
                </span>
                <span class="text-slate-700 dark:text-slate-200">Same as light mode</span>
            </button>
        </template>

        <template x-for="group in paletteGroups" :key="group.name">
            <div class="rounded-lg px-2 py-1.5 hover:bg-slate-50/70 dark:hover:bg-slate-800/40">
                <button type="button" x-on:click="choose(group.name)" class="flex w-full cursor-pointer items-center gap-2.5 text-left">
                    <span
                        class="media-checker relative h-6 w-6 shrink-0 overflow-hidden rounded-md ring-1 ring-black/10 ring-inset dark:ring-white/15"
                    >
                        <span class="absolute inset-0" x-bind:style="'background:' + palette[group.name]"></span>
                    </span>
                    <span class="min-w-0 flex-1 truncate font-mono text-xs text-slate-700 dark:text-slate-200" x-text="group.name"></span>
                    <x-admin.icon
                        name="check"
                        class="h-4 w-4 text-blue-600 dark:text-blue-400"
                        x-show="picker.slot && slotValue(picker.slot, picker.mode) === group.name"
                    />
                </button>
                <template x-if="group.shades.length">
                    <div class="mt-1.5 ml-8.5 flex gap-0.5">
                        <template x-for="shade in group.shades" :key="shade">
                            <button
                                type="button"
                                x-on:click="choose(shade)"
                                x-bind:style="'background:' + palette[shade]"
                                x-bind:title="shade"
                                x-bind:class="
                                    picker.slot && slotValue(picker.slot, picker.mode) === shade
                                        ? 'ring-2 ring-blue-500 ring-offset-1 dark:ring-offset-slate-900'
                                        : 'ring-1 ring-black/5 ring-inset'
                                "
                                class="h-5 flex-1 cursor-pointer rounded-[4px] transition hover:scale-110"
                                x-bind:aria-label="shade"
                            ></button>
                        </template>
                    </div>
                </template>
            </div>
        </template>

        <button
            type="button"
            x-on:click="choose('transparent')"
            class="mt-1 flex w-full cursor-pointer items-center gap-2.5 rounded-lg px-2 py-1.5 text-left hover:bg-slate-50 dark:hover:bg-slate-800"
        >
            <span class="media-checker h-6 w-6 shrink-0 rounded-md ring-1 ring-black/10 ring-inset"></span>
            <span class="font-mono text-xs text-slate-700 dark:text-slate-200">transparent</span>
        </button>

        <div class="mt-2 border-t border-slate-100 px-2 pt-2 dark:border-slate-800">
            <button
                type="button"
                x-on:click="
                    closePicker()
                    tab = 'colors'
                    addVariable()
                "
                class="flex cursor-pointer items-center gap-1.5 text-xs font-semibold text-blue-600 hover:underline dark:text-blue-400"
            >
                <x-admin.icon name="plus" class="h-3.5 w-3.5" />
                New colour
            </button>
        </div>
    </div>
</template>
