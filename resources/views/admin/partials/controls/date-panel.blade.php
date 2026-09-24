<template x-teleport="#admin-portal">
    <div
        x-ref="panel"
        x-show="open"
        x-transition.opacity.duration.100ms
        x-bind:style="menuStyle"
        x-on:keydown.escape.prevent.stop="hide()"
        role="dialog"
        aria-modal="false"
        aria-label="{{ $panelLabel }}"
        class="z-[120] rounded-xl border border-slate-200 bg-white p-3 shadow-lg dark:border-slate-700 dark:bg-slate-900"
    >
        <template x-if="mode === 'range'">
            <div class="mb-3 flex flex-wrap gap-1 border-b border-slate-100 pb-3 dark:border-slate-800">
                <template x-for="preset in presets" :key="preset.key">
                    <button
                        type="button"
                        x-on:click="applyPreset(preset.key)"
                        x-text="preset.label"
                        class="cursor-pointer rounded-md border border-slate-200 px-2 py-1 text-[11px] font-medium text-slate-600 transition hover:border-blue-300 hover:bg-blue-50 hover:text-blue-700 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-blue-500/10"
                    ></button>
                </template>
            </div>
        </template>

        <div class="mb-2 flex items-center justify-between">
            <button
                type="button"
                x-on:click="step(-1)"
                x-bind:aria-label="{ days: 'Previous month', months: 'Previous year', years: 'Previous years' }[picker]"
                class="flex h-7 w-7 cursor-pointer items-center justify-center rounded-md text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800"
            >
                <x-admin.icon name="chevron-left" class="h-4 w-4" />
            </button>
            <button
                type="button"
                x-on:click="zoomOut()"
                x-bind:aria-label="
                    picker === 'days'
                        ? 'Choose month and year'
                        : picker === 'months'
                          ? 'Choose year'
                          : 'Back to months'
                "
                class="inline-flex cursor-pointer items-center gap-1 rounded-md px-2 py-1 text-sm font-semibold text-slate-800 transition hover:bg-slate-100 dark:text-slate-100 dark:hover:bg-slate-800"
            >
                <span x-text="headerLabel" aria-live="polite"></span>
                <x-admin.icon name="chevron-down" class="h-3.5 w-3.5 text-slate-400 transition" x-bind:class="picker !== 'days' && 'rotate-180'" />
            </button>
            <button
                type="button"
                x-on:click="step(1)"
                x-bind:aria-label="{ days: 'Next month', months: 'Next year', years: 'Next years' }[picker]"
                class="flex h-7 w-7 cursor-pointer items-center justify-center rounded-md text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800"
            >
                <x-admin.icon name="chevron-right" class="h-4 w-4" />
            </button>
        </div>

        <div x-show="picker === 'months'" class="grid h-56 grid-cols-3 grid-rows-4 gap-1.5">
            <template x-for="month in monthNames" :key="month.index">
                <button
                    type="button"
                    x-on:click="chooseMonth(month.index)"
                    x-bind:disabled="isDisabledMonth(month.index)"
                    x-text="month.name"
                    x-bind:class="
                        month.index === view.getMonth()
                            ? 'bg-blue-600 font-semibold text-white hover:bg-blue-600 dark:bg-blue-500'
                            : isCurrentMonth(month.index)
                              ? 'font-semibold text-slate-700 ring-1 ring-blue-400 ring-inset dark:text-slate-200'
                              : 'text-slate-700 dark:text-slate-200'
                    "
                    class="cursor-pointer rounded-lg text-sm hover:bg-slate-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 disabled:cursor-not-allowed disabled:opacity-40 dark:hover:bg-slate-800"
                ></button>
            </template>
        </div>

        <div x-show="picker === 'years'" class="grid h-56 grid-cols-3 grid-rows-4 gap-1.5">
            <template x-for="year in years" :key="year">
                <button
                    type="button"
                    x-on:click="chooseYear(year)"
                    x-bind:disabled="isDisabledYear(year)"
                    x-text="year"
                    x-bind:class="
                        year === view.getFullYear()
                            ? 'bg-blue-600 font-semibold text-white hover:bg-blue-600 dark:bg-blue-500'
                            : year === new Date().getFullYear()
                              ? 'font-semibold text-slate-700 ring-1 ring-blue-400 ring-inset dark:text-slate-200'
                              : 'text-slate-700 dark:text-slate-200'
                    "
                    class="tabular cursor-pointer rounded-lg text-sm hover:bg-slate-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 disabled:cursor-not-allowed disabled:opacity-40 dark:hover:bg-slate-800"
                ></button>
            </template>
        </div>

        <div
            x-show="picker === 'days'"
            class="grid grid-cols-7 place-items-center gap-0.5 text-center"
            x-on:keydown="gridKey($event)"
            x-on:mouseleave="hoverDay = ''"
        >
            <template x-for="weekday in weekdays" :key="weekday">
                <span class="py-1 text-[11px] font-medium text-slate-400" x-text="weekday" aria-hidden="true"></span>
            </template>

            <template x-for="cell in cells" :key="cell.date">
                <button
                    type="button"
                    x-bind:data-date="cell.date"
                    x-bind:tabindex="cell.date === focusDay ? 0 : -1"
                    x-bind:disabled="isDisabledDay(cell.date)"
                    x-bind:aria-pressed="isSelected(cell.date).toString()"
                    x-bind:aria-label="
                        new Date(cell.date + 'T00:00').toLocaleDateString(undefined, {
                            weekday: 'long',
                            day: 'numeric',
                            month: 'long',
                            year: 'numeric',
                        })
                    "
                    x-on:click="pick(cell.date)"
                    x-on:focus="focusDay = cell.date"
                    x-on:mouseenter="hoverDay = cell.date"
                    x-text="cell.day"
                    x-bind:class="{
                        'bg-blue-600 font-semibold text-white hover:bg-blue-600 dark:bg-blue-500':
                            isSelected(cell.date),
                        'bg-blue-50 text-blue-700 dark:bg-blue-500/15 dark:text-blue-200':
                            inRange(cell.date) && ! isSelected(cell.date),
                        'text-slate-300 dark:text-slate-600':
                            cell.outside && ! isSelected(cell.date),
                        'text-slate-700 dark:text-slate-200':
                            ! cell.outside && ! isSelected(cell.date) && ! inRange(cell.date),
                        'font-semibold ring-1 ring-blue-400 ring-inset':
                            isToday(cell.date) && ! isSelected(cell.date),
                    }"
                    class="tabular h-8 w-8 cursor-pointer rounded-lg text-xs hover:bg-slate-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 disabled:cursor-not-allowed disabled:opacity-40 dark:hover:bg-slate-800"
                ></button>
            </template>
        </div>

        <template x-if="withTime">
            <div class="mt-3 flex items-center gap-2 border-t border-slate-100 pt-3 dark:border-slate-800">
                <x-admin.icon name="clock" class="h-4 w-4 text-slate-400" />
                <select
                    x-model="hour"
                    x-on:change="setTime()"
                    aria-label="Hour"
                    class="h-8 rounded-lg border border-slate-200 bg-white px-2 text-sm dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                >
                    <template x-for="h in hours" :key="h">
                        <option x-bind:value="h" x-text="h" x-bind:selected="h === hour"></option>
                    </template>
                </select>
                <span class="text-slate-400">:</span>
                <select
                    x-model="minute"
                    x-on:change="setTime()"
                    aria-label="Minute"
                    class="h-8 rounded-lg border border-slate-200 bg-white px-2 text-sm dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                >
                    <template x-for="m in minutes" :key="m">
                        <option x-bind:value="m" x-text="m" x-bind:selected="m === minute"></option>
                    </template>
                </select>
            </div>
        </template>

        <div class="mt-3 flex items-center justify-between gap-2 border-t border-slate-100 pt-3 dark:border-slate-800">
            <template x-if="mode !== 'range'">
                <button
                    type="button"
                    x-on:click="today()"
                    class="cursor-pointer text-xs font-semibold text-blue-600 hover:text-blue-700 dark:text-blue-400"
                >
                    Today
                </button>
            </template>
            <template x-if="mode === 'range'">
                <span
                    class="text-[11px] text-slate-400"
                    x-text="value.start && ! value.end ? 'Now pick the end date' : 'Pick a start date'"
                ></span>
            </template>

            <div class="flex items-center gap-3">
                @if ($clearable)
                    <button
                        type="button"
                        x-on:click="clear()"
                        class="cursor-pointer text-xs font-semibold text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-white"
                    >
                        Clear
                    </button>
                @endif

                <button
                    type="button"
                    x-on:click="hide()"
                    class="cursor-pointer rounded-md bg-blue-600 px-2.5 py-1 text-xs font-semibold text-white hover:bg-blue-700"
                >
                    Done
                </button>
            </div>
        </div>
    </div>
</template>
