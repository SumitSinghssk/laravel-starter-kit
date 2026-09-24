@props(["name" => "search", "placeholder" => "Search…"])

@php
    $term = trim((string) request($name));
@endphp

<div
    x-data="{
        open: false,
        term: @js($term),
        timer: null,
        focusKey: 'admin-filter-search:' + window.location.pathname,
        init() {
            try {
                if (sessionStorage.getItem(this.focusKey)) {
                    sessionStorage.removeItem(this.focusKey)
                    this.show()
                }
            } catch (e) {}
        },
        show() {
            this.open = true
            this.$nextTick(() => {
                const input = this.$refs.input
                input.focus()
                input.setSelectionRange(input.value.length, input.value.length)
            })
        },
        close() {
            this.open = false
        },
        search(delay = 0) {
            clearTimeout(this.timer)
            this.timer = setTimeout(() => {
                try {
                    sessionStorage.setItem(this.focusKey, '1')
                } catch (e) {}
                this.$dispatch('filter-submit')
            }, delay)
        },
        clear() {
            this.term = ''
            this.$nextTick(() => this.$dispatch('filter-submit'))
        },
    }"
    x-on:keydown.escape.prevent.stop="close()"
    x-on:click.outside="close()"
    class="relative"
>
    <div class="flex items-center">
        <button
            type="button"
            x-on:click="open ? close() : show()"
            x-bind:aria-expanded="open.toString()"
            aria-controls="filter-search-panel"
            @if ($term)
                aria-label="Search: {{ $term }}. Change search"
            @else
                aria-label="Search"
            @endif
            title="Search"
            @class([
                "inline-flex h-8 cursor-pointer items-center gap-1.5 text-xs font-medium transition focus:outline-none focus-visible:ring-3 focus-visible:ring-blue-500/30",
                "w-8 justify-center rounded-lg border border-slate-200 bg-white text-slate-500 shadow-xs hover:border-slate-300 hover:text-slate-900 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-400 dark:hover:text-white" => ! $term,
                "max-w-56 rounded-l-lg border border-r-0 border-blue-200 bg-blue-50 pr-2 pl-2.5 text-blue-700 dark:border-blue-500/30 dark:bg-blue-500/10 dark:text-blue-300" => $term,
            ])
            x-bind:class="open && 'ring-3 ring-blue-500/15 border-blue-300'"
        >
            <x-admin.icon name="search" class="h-4 w-4" />
            @if ($term)
                <span class="truncate">“{{ $term }}”</span>
            @endif
        </button>

        @if ($term)
            <button
                type="button"
                x-on:click="clear()"
                aria-label="Clear search"
                title="Clear search"
                class="inline-flex h-8 w-7 cursor-pointer items-center justify-center rounded-r-lg border border-blue-200 bg-blue-50 text-blue-500 transition hover:bg-blue-100 hover:text-blue-700 dark:border-blue-500/30 dark:bg-blue-500/10 dark:text-blue-300"
            >
                <x-admin.icon name="x" class="h-3.5 w-3.5" />
            </button>
        @endif
    </div>

    <div
        id="filter-search-panel"
        x-show="open"
        x-cloak
        x-transition:enter="transition duration-150 ease-out"
        x-transition:enter-start="-translate-y-1 opacity-0"
        x-transition:enter-end="translate-y-0 opacity-100"
        class="absolute top-full right-0 z-50 mt-2 w-[min(22rem,calc(100vw-2rem))] rounded-xl border border-slate-200 bg-white p-2 shadow-lg dark:border-slate-700 dark:bg-slate-900"
    >
        <div class="relative">
            <x-admin.icon name="search" class="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-slate-400" />
            <input
                x-ref="input"
                type="search"
                name="{{ $name }}"
                x-model="term"
                value="{{ $term }}"
                x-on:input="search(500)"
                x-on:keydown.enter.prevent="search(0)"
                placeholder="{{ $placeholder }}"
                aria-label="{{ $placeholder }}"
                autocomplete="off"
                class="h-9 w-full rounded-lg border border-slate-200 bg-slate-50 pr-3 pl-9 text-sm text-slate-900 placeholder:text-slate-400 focus:border-blue-400 focus:bg-white focus:ring-3 focus:ring-blue-500/15 focus:outline-none dark:border-slate-700 dark:bg-slate-800 dark:text-white dark:focus:bg-slate-900 [&::-webkit-search-cancel-button]:hidden"
            />
        </div>
        <p class="mt-2 flex items-center justify-between px-1 text-[11px] text-slate-400">
            <span>Results update as you type</span>
            <span class="flex items-center gap-1">
                <kbd class="rounded border border-slate-200 px-1 font-sans dark:border-slate-700">Enter</kbd>
                search
                <kbd class="ml-1 rounded border border-slate-200 px-1 font-sans dark:border-slate-700">Esc</kbd>
                close
            </span>
        </p>
    </div>
</div>
