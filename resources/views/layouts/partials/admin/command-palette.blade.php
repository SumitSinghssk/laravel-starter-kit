@php
    $user = auth('web')->user();

    $paletteItems = collect($links)
        ->flatMap(
            fn ($section) => collect($section['items'])
                ->filter(fn ($link) => ! isset($link['permission']) || $user->can($link['permission']))
                ->map(fn ($link) => ['group' => 'Go to', 'title' => $link['title'], 'hint' => $section['section'], 'icon' => $link['icon'], 'url' => $link['route']]),
        )
        ->merge(collect($quickCreate ?? [])->map(fn ($item) => ['group' => 'Create', 'title' => 'New ' . strtolower($item['title']), 'hint' => '', 'icon' => 'plus', 'url' => $item['url']]))
        ->when($user->can('profile.view'), fn ($items) => $items->push(['group' => 'Account', 'title' => 'My profile', 'hint' => '', 'icon' => 'user-circle', 'url' => route('admin.profile.edit')]))
        ->when($user->can('admin.notifications.view'), fn ($items) => $items->push(['group' => 'Account', 'title' => 'Notifications', 'hint' => '', 'icon' => 'bell', 'url' => route('admin.notifications.list')]))
        ->push(['group' => 'Account', 'title' => 'View website', 'hint' => 'Opens in a new tab', 'icon' => 'external-link', 'url' => route('home'), 'external' => true])
        ->values();
@endphp

<div
    x-data="{
        open: false,
        query: '',
        active: 0,
        items: @js($paletteItems),
        get results() {
            const q = this.query.trim().toLowerCase()
            return q
                ? this.items.filter((item) =>
                      (item.title + ' ' + item.hint + ' ' + item.group)
                          .toLowerCase()
                          .includes(q),
                  )
                : this.items
        },
        groupBefore(index) {
            const item = this.results[index]
            return item.group !== this.results[index - 1]?.group ? item.group : ''
        },
        show() {
            this.open = true
            this.query = ''
            this.active = 0
            this.$nextTick(() => this.$refs.input.focus())
        },
        go(item) {
            if (! item) return
            this.open = false
            item.external
                ? window.open(item.url, '_blank', 'noopener')
                : (window.location.href = item.url)
        },
        move(step) {
            const count = this.results.length
            if (! count) return
            this.active = (this.active + step + count) % count
            this.$nextTick(() =>
                this.$refs.list
                    .querySelector(`[data-index='${this.active}']`)
                    ?.scrollIntoView({ block: 'nearest' }),
            )
        },
    }"
    x-on:open-command-palette.window="show()"
    x-on:keydown.window="
        if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') {
            event.preventDefault()
            open ? (open = false) : show()
        }
    "
>
    <div
        x-show="open"
        x-cloak
        class="fixed inset-0 z-[130] flex items-start justify-center px-4 pt-[12vh]"
        role="dialog"
        aria-modal="true"
        aria-label="Search"
    >
        <div
            x-show="open"
            x-transition.opacity.duration.150ms
            x-on:click="open = false"
            class="absolute inset-0 bg-slate-950/40 backdrop-blur-[2px]"
        ></div>

        <div
            x-show="open"
            x-transition:enter="transition duration-150 ease-out"
            x-transition:enter-start="scale-[0.98] opacity-0"
            x-transition:enter-end="scale-100 opacity-100"
            x-on:keydown.tab.prevent="move(event.shiftKey ? -1 : 1)"
            x-on:keydown.escape.prevent.stop="open = false"
            class="relative w-full max-w-xl overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl dark:border-slate-800 dark:bg-slate-900"
        >
            <div class="flex items-center gap-3 border-b border-slate-100 px-4 dark:border-slate-800">
                <x-admin.icon name="search" class="h-4.5 w-4.5 text-slate-400" />
                <input
                    x-ref="input"
                    x-model="query"
                    x-on:input="active = 0"
                    x-on:keydown.arrow-down.prevent="move(1)"
                    x-on:keydown.arrow-up.prevent="move(-1)"
                    x-on:keydown.enter.prevent="go(results[active])"
                    type="text"
                    role="combobox"
                    aria-expanded="true"
                    aria-controls="command-palette-list"
                    x-bind:aria-activedescendant="results.length ? 'command-item-' + active : null"
                    placeholder="Search pages and actions…"
                    class="h-13 flex-1 bg-transparent text-base text-slate-900 placeholder:text-slate-400 focus:outline-none dark:text-white"
                />
                <kbd
                    class="rounded-md border border-slate-200 px-1.5 py-0.5 font-sans text-[10px] font-semibold text-slate-400 dark:border-slate-700"
                >
                    ESC
                </kbd>
            </div>

            <div x-ref="list" id="command-palette-list" role="listbox" class="custom-scrollbar max-h-[55vh] overflow-y-auto p-2">
                <template x-for="(item, index) in results" :key="item.group + item.url">
                    <div role="presentation">
                        <p
                            x-show="groupBefore(index)"
                            x-text="groupBefore(index)"
                            class="px-2.5 pt-2.5 pb-1 text-[11px] font-medium text-slate-400"
                        ></p>
                        <button
                            type="button"
                            role="option"
                            x-bind:id="'command-item-' + index"
                            x-bind:data-index="index"
                            x-bind:aria-selected="(active === index).toString()"
                            x-on:click="go(item)"
                            x-on:mousemove="active = index"
                            x-bind:class="active === index ? 'bg-slate-100 dark:bg-slate-800' : ''"
                            class="flex w-full cursor-pointer items-center gap-3 rounded-lg px-2.5 py-2 text-left"
                        >
                            <span
                                class="flex h-7 w-7 shrink-0 items-center justify-center rounded-md border border-slate-200 bg-white text-slate-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-400"
                            >
                                @foreach ($paletteItems->pluck('icon')->unique() as $iconName)
                                    <x-admin.icon :name="$iconName" x-show="item.icon === {{ Js::from($iconName) }}" class="h-3.5 w-3.5" />
                                @endforeach
                            </span>
                            <span class="flex-1 truncate text-sm font-medium text-slate-800 dark:text-slate-100" x-text="item.title"></span>
                            <span class="text-xs text-slate-400" x-text="item.hint"></span>
                            <x-admin.icon name="arrow-right" x-show="active === index" class="h-3.5 w-3.5 text-slate-400" />
                        </button>
                    </div>
                </template>

                <div x-show="! results.length" class="px-4 py-10 text-center">
                    <p class="text-sm font-medium text-slate-700 dark:text-slate-200">No results</p>
                    <p class="mt-0.5 text-xs text-slate-500">Try a page name like “Blogs” or “Settings”.</p>
                </div>
            </div>

            <div
                class="flex items-center gap-4 border-t border-slate-100 bg-slate-50 px-4 py-2 text-[11px] text-slate-500 dark:border-slate-800 dark:bg-slate-900/60"
            >
                <span class="flex items-center gap-1">
                    <kbd class="rounded border border-slate-200 bg-white px-1 font-sans dark:border-slate-700 dark:bg-slate-800">↑</kbd>
                    <kbd class="rounded border border-slate-200 bg-white px-1 font-sans dark:border-slate-700 dark:bg-slate-800">↓</kbd>
                    to move
                </span>
                <span class="flex items-center gap-1">
                    <kbd class="rounded border border-slate-200 bg-white px-1 font-sans dark:border-slate-700 dark:bg-slate-800">Enter</kbd>
                    to open
                </span>
            </div>
        </div>
    </div>
</div>
