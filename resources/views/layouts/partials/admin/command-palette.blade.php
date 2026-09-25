@php
    use App\Services\AdminSearch;
    use App\Support\AdminSearchIndex;
    use Illuminate\Support\Facades\Blade;

    $user = auth('web')->user();
    $pageKeywords = AdminSearchIndex::pageKeywords();

    $paletteItems = collect($links)
        ->flatMap(
            fn ($section) => collect($section['items'])
                ->filter(fn ($link) => ! isset($link['permission']) || $user->can($link['permission']))
                ->map(
                    fn ($link) => [
                        'group' => 'Go to',
                        'title' => $link['title'],
                        'hint' => $section['section'],
                        'icon' => $link['icon'],
                        'url' => $link['route'],
                        'keywords' => $pageKeywords[$link['title']] ?? '',
                    ],
                ),
        )
        ->merge(AdminSearchIndex::shortcuts($user))
        ->merge(collect($quickCreate ?? [])->map(fn ($item) => ['group' => 'Create', 'title' => 'New ' . strtolower($item['title']), 'hint' => '', 'icon' => 'plus', 'url' => $item['url'], 'keywords' => 'add create new']))
        ->when($user->can('profile.view'), fn ($items) => $items->push(['group' => 'Account', 'title' => 'My profile', 'hint' => '', 'icon' => 'user-circle', 'url' => route('admin.profile.edit'), 'keywords' => 'account password avatar name']))
        ->when($user->can('admin.notifications.view'), fn ($items) => $items->push(['group' => 'Account', 'title' => 'Notifications', 'hint' => '', 'icon' => 'bell', 'url' => route('admin.notifications.list'), 'keywords' => 'alerts bell']))
        ->push(['group' => 'Account', 'title' => 'View website', 'hint' => 'Opens in a new tab', 'icon' => 'external-link', 'url' => route('home'), 'external' => true, 'keywords' => 'site frontend public'])
        ->values();

    $iconNames = $paletteItems
        ->pluck('icon')
        ->merge(['search', 'arrow-right', 'inbox', 'users', 'newspaper', 'tag', 'file-text', 'image', 'message', 'redirect', 'globe', 'folder', 'history'])
        ->unique()
        ->values();
    $paletteIcons = $iconNames->mapWithKeys(fn ($name) => [$name => trim(Blade::render('<x-admin.icon :name="$name" class="h-3.5 w-3.5" />', ['name' => $name]))]);
@endphp

<div
    x-data="commandPalette(
                {{
                    Js::from([
                        'items' => $paletteItems,
                        'icons' => $paletteIcons,
                        'url' => route('admin.search'),
                        'minLength' => AdminSearch::MIN_LENGTH,
                    ])
                }},
            )"
    x-on:open-command-palette.window="show()"
    x-on:keydown.window="
        if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') {
            event.preventDefault()
            open ? close() : show()
        } else if (
            event.key === '/' &&
            ! open &&
            ! ['INPUT', 'TEXTAREA', 'SELECT'].includes(
                document.activeElement?.tagName,
            ) &&
            ! document.activeElement?.isContentEditable
        ) {
            event.preventDefault()
            show()
        }
    "
>
    <div
        x-show="open"
        x-cloak
        class="fixed inset-0 z-[130] flex items-start justify-center px-4 pt-[10vh]"
        role="dialog"
        aria-modal="true"
        aria-label="Search"
    >
        <div
            x-show="open"
            x-transition.opacity.duration.150ms
            x-on:click="close()"
            class="absolute inset-0 bg-slate-950/40 backdrop-blur-[2px]"
        ></div>

        <div
            x-show="open"
            x-transition:enter="transition duration-150 ease-out"
            x-transition:enter-start="scale-[0.98] opacity-0"
            x-transition:enter-end="scale-100 opacity-100"
            x-on:keydown.tab.prevent="move(event.shiftKey ? -1 : 1)"
            x-on:keydown.escape.prevent.stop="close()"
            class="relative w-full max-w-2xl overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl dark:border-slate-800 dark:bg-slate-900"
        >
            <div class="flex items-center gap-3 border-b border-slate-100 px-4 dark:border-slate-800">
                <x-admin.icon name="search" x-show="! loading" class="h-4.5 w-4.5 text-slate-400" />
                <x-admin.icon name="refresh" x-show="loading" x-cloak class="h-4.5 w-4.5 animate-spin text-blue-500" />
                <input
                    x-ref="input"
                    x-model="query"
                    x-on:keydown.arrow-down.prevent="move(1)"
                    x-on:keydown.arrow-up.prevent="move(-1)"
                    x-on:keydown.enter.prevent="submit(event.ctrlKey || event.metaKey)"
                    type="text"
                    role="combobox"
                    aria-expanded="true"
                    aria-autocomplete="list"
                    aria-controls="command-palette-list"
                    x-bind:aria-activedescendant="flat.length ? 'command-item-' + active : null"
                    placeholder="Search people, enquiries, content, settings…"
                    autocomplete="off"
                    spellcheck="false"
                    class="h-13 min-w-0 flex-1 bg-transparent text-base text-slate-900 placeholder:text-slate-400 focus:outline-none dark:text-white"
                />
                <button
                    type="button"
                    x-show="query"
                    x-cloak
                    x-on:click="
                        query = ''
                        $refs.input.focus()
                    "
                    class="cursor-pointer rounded-md p-1 text-slate-400 transition hover:bg-slate-100 hover:text-slate-600 dark:hover:bg-slate-800"
                    aria-label="Clear search"
                >
                    <x-admin.icon name="x" class="h-4 w-4" />
                </button>
                <kbd
                    class="hidden rounded-md border border-slate-200 px-1.5 py-0.5 font-sans text-[10px] font-semibold text-slate-400 sm:block dark:border-slate-700"
                >
                    ESC
                </kbd>
            </div>

            <div
                x-ref="list"
                id="command-palette-list"
                role="listbox"
                x-bind:aria-busy="stale.toString()"
                class="custom-scrollbar max-h-[60vh] overflow-y-auto p-2"
            >
                <template x-for="section in sections" :key="section.key">
                    <div
                        role="group"
                        x-bind:aria-label="section.label"
                        x-bind:class="
                            stale && ! section.key.startsWith('static:') && section.key !== 'recent'
                                ? 'opacity-60 transition-opacity'
                                : ''
                        "
                    >
                        <div class="flex items-center justify-between gap-2 px-2.5 pt-2.5 pb-1">
                            <p class="text-[11px] font-medium text-slate-400">
                                <span x-text="section.label"></span>
                                <span
                                    x-show="section.total > section.items.filter((item) => item.kind === 'record').length"
                                    x-text="'· ' + section.total"
                                ></span>
                            </p>
                            <button
                                type="button"
                                x-show="section.clearable"
                                x-on:click="
                                    clearRecent()
                                    $refs.input.focus()
                                "
                                class="cursor-pointer text-[11px] font-medium text-slate-400 hover:text-slate-600 dark:hover:text-slate-200"
                            >
                                Clear
                            </button>
                        </div>

                        <template x-for="item in section.items" :key="item.id">
                            <button
                                type="button"
                                role="option"
                                x-bind:id="'command-item-' + item.index"
                                x-bind:data-index="item.index"
                                x-bind:aria-selected="(active === item.index).toString()"
                                x-on:click="go(item, $event.ctrlKey || $event.metaKey)"
                                x-on:mousemove="active = item.index"
                                x-bind:class="active === item.index ? 'bg-slate-100 dark:bg-slate-800' : ''"
                                class="flex w-full cursor-pointer items-center gap-3 rounded-lg px-2.5 py-2 text-left"
                            >
                                <span
                                    x-bind:class="
                                        item.kind === 'more'
                                            ? 'border-transparent bg-transparent text-blue-600 dark:text-blue-400'
                                            : 'border-slate-200 bg-white text-slate-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-400'
                                    "
                                    class="flex h-7 w-7 shrink-0 items-center justify-center rounded-md border"
                                    x-html="icon(item.kind === 'recent' ? 'history' : item.icon)"
                                ></span>
                                <span class="min-w-0 flex-1">
                                    <span
                                        class="block truncate text-sm font-medium"
                                        x-bind:class="
                                            item.kind === 'more'
                                                ? 'text-blue-600 dark:text-blue-400'
                                                : 'text-slate-800 dark:text-slate-100'
                                        "
                                        x-html="mark(item.title)"
                                    ></span>
                                    <span
                                        x-show="item.subtitle"
                                        class="block truncate text-xs text-slate-500 dark:text-slate-400"
                                        x-html="mark(item.subtitle)"
                                    ></span>
                                </span>
                                <span
                                    x-show="item.badge"
                                    x-text="item.badge"
                                    class="shrink-0 rounded-full bg-slate-100 px-2 py-px text-[11px] font-medium text-slate-600 dark:bg-slate-800 dark:text-slate-300"
                                ></span>
                                <span
                                    x-show="item.kind === 'static' && item.hint"
                                    x-text="item.hint"
                                    class="hidden shrink-0 text-xs text-slate-400 sm:inline"
                                ></span>
                                <x-admin.icon name="arrow-right" x-show="active === item.index" class="h-3.5 w-3.5 shrink-0 text-slate-400" />
                            </button>
                        </template>
                    </div>
                </template>

                <div x-show="stale && ! showGroups" class="space-y-2 px-2.5 py-3" aria-hidden="true">
                    <div class="h-3 w-24 animate-pulse rounded bg-slate-100 dark:bg-slate-800"></div>
                    <div class="h-9 animate-pulse rounded-lg bg-slate-100 dark:bg-slate-800"></div>
                    <div class="h-9 animate-pulse rounded-lg bg-slate-100 dark:bg-slate-800"></div>
                </div>

                <p x-show="failed" x-cloak class="px-3 py-3 text-xs text-red-600 dark:text-red-400">
                    Records could not be searched just now. Check your connection and try again.
                </p>

                <div x-show="! flat.length && ! pending" class="px-4 py-10 text-center">
                    <p class="text-sm font-medium text-slate-700 dark:text-slate-200" x-text="'No results for “' + query.trim() + '”'"></p>
                    <p class="mt-0.5 text-xs text-slate-500">
                        Try a name, email, enquiry number like ENQ-00012, a page title or a setting like “SMTP”.
                    </p>
                </div>

                <p x-show="query.trim() && ! searching" class="px-3 pt-2 pb-1 text-xs text-slate-400">
                    Type one more letter to search records too.
                </p>
            </div>

            <div
                class="flex flex-wrap items-center gap-x-4 gap-y-1 border-t border-slate-100 bg-slate-50 px-4 py-2 text-[11px] text-slate-500 dark:border-slate-800 dark:bg-slate-900/60"
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
                <span class="hidden items-center gap-1 sm:flex">
                    <kbd class="rounded border border-slate-200 bg-white px-1 font-sans dark:border-slate-700 dark:bg-slate-800">Ctrl</kbd>
                    <kbd class="rounded border border-slate-200 bg-white px-1 font-sans dark:border-slate-700 dark:bg-slate-800">Enter</kbd>
                    new tab
                </span>
                <span class="ml-auto hidden sm:inline">Only shows what you have access to</span>
            </div>
        </div>
    </div>
</div>
