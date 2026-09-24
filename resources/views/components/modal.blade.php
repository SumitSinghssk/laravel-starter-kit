@props([
    'name',
    'show' => false,
    'maxWidth' => '2xl',
])

@php
    $maxWidth = [
        'sm' => 'sm:max-w-sm',
        'md' => 'sm:max-w-md',
        'lg' => 'sm:max-w-lg',
        'xl' => 'sm:max-w-xl',
        '2xl' => 'sm:max-w-2xl',
        '4xl' => 'sm:max-w-4xl',
    ][$maxWidth];
@endphp

<div x-data style="display: contents">
    <template x-teleport="#admin-portal">
        <div
            x-data="{
        show: @js($show),
        returnFocus: null,
        focusables() {
            let selector = 'a, button, input:not([type=\'hidden\']), textarea, select, details, [tabindex]:not([tabindex=\'-1\'])'
            return [...$el.querySelectorAll(selector)].filter(el => ! el.hasAttribute('disabled'))
        },
        firstFocusable() { return this.focusables()[0] },
        lastFocusable() { return this.focusables().slice(-1)[0] },
        nextFocusable() { return this.focusables()[this.nextFocusableIndex()] || this.firstFocusable() },
        prevFocusable() { return this.focusables()[this.prevFocusableIndex()] || this.lastFocusable() },
        nextFocusableIndex() { return (this.focusables().indexOf(document.activeElement) + 1) % (this.focusables().length + 1) },
        prevFocusableIndex() { return Math.max(0, this.focusables().indexOf(document.activeElement)) -1 },
    }"
            x-init="
                $watch('show', (value) => {
                    if (value) {
                        document.body.classList.add('overflow-y-hidden')
                        returnFocus = document.activeElement
                        $nextTick(() => firstFocusable()?.focus())
                    } else {
                        document.body.classList.remove('overflow-y-hidden')
                        returnFocus?.focus()
                        returnFocus = null
                    }
                })
            "
            x-on:open-modal.window="$event.detail == '{{ $name }}' ? (show = true) : null"
            x-on:close-modal.window="$event.detail == '{{ $name }}' ? (show = false) : null"
            x-on:close.stop="show = false"
            x-on:keydown.escape.window="if (show) show = false"
            x-on:keydown.tab.prevent="$event.shiftKey || nextFocusable().focus()"
            x-on:keydown.shift.tab.prevent="prevFocusable().focus()"
            x-show="show"
            class="fixed inset-0 z-100 flex items-center justify-center overflow-y-auto px-4 py-6 sm:px-0"
            style="display: none"
        >
            <div
                x-show="show"
                class="fixed inset-0 transform transition-all"
                x-on:click="show = false"
                x-transition:enter="duration-300 ease-out"
                x-transition:enter-start="opacity-0"
                x-transition:enter-end="opacity-100"
                x-transition:leave="duration-200 ease-in"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0"
            >
                <div class="absolute inset-0 bg-slate-900/40 dark:bg-slate-950/60"></div>
            </div>

            <div
                x-show="show"
                role="dialog"
                aria-modal="true"
                class="{{ $maxWidth }} relative z-10 w-full transform rounded-2xl bg-white text-left whitespace-normal shadow-2xl ring-1 ring-slate-200 transition-all dark:bg-slate-900 dark:shadow-indigo-500/10 dark:ring-white/10"
                x-transition:enter="duration-300 ease-out"
                x-transition:enter-start="translate-y-4 opacity-0 sm:translate-y-0 sm:scale-95"
                x-transition:enter-end="translate-y-0 opacity-100 sm:scale-100"
                x-transition:leave="duration-200 ease-in"
                x-transition:leave-start="translate-y-0 opacity-100 sm:scale-100"
                x-transition:leave-end="translate-y-4 opacity-0 sm:translate-y-0 sm:scale-95"
            >
                <div class="custom-scrollbar max-h-[90vh] overflow-x-hidden overflow-y-auto">
                    {{ $slot }}
                </div>
            </div>
        </div>
    </template>
</div>
