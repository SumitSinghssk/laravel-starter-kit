@props([
    'align' => 'right',
    'width' => 'w-56',
])

@php($id = 'dropdown-' . \Illuminate\Support\Str::random(8))

<div
    x-data="{
        open: false,
        style: '',
        place() {
            const rect = document.getElementById(@js($id)).getBoundingClientRect()
            const menu = document.getElementById(@js($id . '-menu'))
            const height = menu?.offsetHeight || 200
            const up =
                window.innerHeight - rect.bottom < height + 8 &&
                rect.top > window.innerHeight - rect.bottom
            const x =
                @js($align) === 'left'
                    ? `left:${Math.max(8, rect.left)}px`
                    : `right:${Math.max(8, window.innerWidth - rect.right)}px`
            const y = up
                ? `bottom:${window.innerHeight - rect.top + 4}px`
                : `top:${rect.bottom + 4}px`
            this.style = `position:fixed;${x};${y}`
        },
        show() {
            this.open = true
            this.place()
            this.$nextTick(() => this.place())
            this._reposition = () => this.place()
            window.addEventListener('scroll', this._reposition, true)
            window.addEventListener('resize', this._reposition)
        },
        hide() {
            this.open = false
            window.removeEventListener('scroll', this._reposition, true)
            window.removeEventListener('resize', this._reposition)
        },
        outside(event) {
            if (! this.open) return
            if (
                document.getElementById(@js($id))?.contains(event.target) ||
                document.getElementById(@js($id . '-menu'))?.contains(event.target)
            )
                return
            this.hide()
        },
    }"
    x-on:click.window="outside($event)"
    x-on:keydown.escape.window="open && hide()"
    {{ $attributes->class('relative inline-block') }}
>
    <div id="{{ $id }}" x-on:click="open ? hide() : show()" aria-haspopup="menu" x-bind:aria-expanded="open.toString()">
        {{ $trigger }}
    </div>

    <template x-teleport="#admin-portal">
        <div
            id="{{ $id }}-menu"
            x-show="open"
            x-cloak
            x-transition.opacity.duration.100ms
            x-bind:style="style"
            x-on:click="hide()"
            role="menu"
            class="{{ $width }} z-[120] overflow-hidden rounded-xl border border-slate-200 bg-white py-1 shadow-lg dark:border-slate-700 dark:bg-slate-900"
        >
            {{ $slot }}
        </div>
    </template>
</div>
