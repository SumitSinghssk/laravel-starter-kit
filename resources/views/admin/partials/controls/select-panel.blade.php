<template x-teleport="#admin-portal">
    <div
        x-ref="panel"
        x-show="open"
        x-transition.opacity.duration.100ms
        x-bind:style="menuStyle"
        class="z-[120] flex max-h-72 flex-col overflow-hidden rounded-xl border border-slate-200 bg-white shadow-lg dark:border-slate-700 dark:bg-slate-900"
    >
        <template x-if="searchable">
            <div class="shrink-0 border-b border-slate-100 p-1.5 dark:border-slate-800">
                <div class="relative">
                    <x-admin.icon name="search" class="pointer-events-none absolute top-1/2 left-2.5 h-3.5 w-3.5 -translate-y-1/2 text-slate-400" />
                    <input
                        x-ref="search"
                        x-model="query"
                        x-on:input="active = 0"
                        x-on:keydown="key($event)"
                        type="text"
                        role="combobox"
                        aria-autocomplete="list"
                        x-bind:aria-controls="uid + '-listbox'"
                        x-bind:aria-activedescendant="filtered.length ? uid + '-option-' + active : null"
                        aria-label="Search options"
                        placeholder="Search…"
                        class="h-8 w-full rounded-lg border border-slate-200 bg-white pr-2 pl-8 text-sm text-slate-900 focus:border-blue-400 focus:ring-2 focus:ring-blue-500/15 focus:outline-none dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                    />
                </div>
            </div>
        </template>

        <div
            x-bind:id="uid + '-listbox'"
            role="listbox"
            @if (filled($listLabel ?? null)) aria-label="{{ $listLabel }}" @endif
            class="custom-scrollbar overflow-y-auto p-1"
        >
            <template x-for="(option, index) in filtered" :key="option.value">
                <div role="presentation">
                    <div
                        x-show="groupBefore(index)"
                        x-text="groupBefore(index)"
                        role="presentation"
                        class="px-2.5 pt-2 pb-1 text-[11px] font-medium text-slate-400 select-none"
                    ></div>
                    <div
                        role="option"
                        x-bind:id="uid + '-option-' + index"
                        x-bind:data-index="index"
                        x-bind:aria-selected="(option.value === value).toString()"
                        x-on:click="choose(option)"
                        x-on:mousemove="active = index"
                        x-bind:class="{
                            'bg-slate-100 dark:bg-slate-800': active === index,
                            'font-medium text-slate-900 dark:text-white': option.value === value,
                            'text-slate-700 dark:text-slate-200': option.value !== value,
                        }"
                        x-bind:style="option.depth ? `padding-left: ${0.625 + option.depth * 1}rem` : ''"
                        class="flex cursor-pointer items-center gap-2 rounded-lg px-2.5 py-1.5 text-sm"
                    >
                        <span x-show="option.dot" x-bind:class="option.dot" class="h-2 w-2 shrink-0 rounded-full"></span>
                        <span class="min-w-0 flex-1">
                            <span class="block truncate" x-text="option.label"></span>
                            <span
                                x-show="option.description"
                                x-text="option.description"
                                class="block truncate text-xs font-normal text-slate-500 dark:text-slate-400"
                            ></span>
                        </span>
                        <x-admin.icon name="check" x-show="option.value === value" class="h-4 w-4 text-blue-600 dark:text-blue-400" stroke="2.2" />
                    </div>
                </div>
            </template>

            <p x-show="! filtered.length" class="px-2.5 py-2 text-sm text-slate-500 dark:text-slate-400">
                <span x-text="query.trim() ? 'No matches' : @js($emptyMessage ?? 'No options')"></span>
            </p>
        </div>
    </div>
</template>
