<div {{ $attributes->class('grid grid-cols-1 items-start gap-6 xl:grid-cols-[minmax(0,1fr)_22rem]') }}>
    <div class="min-w-0 space-y-6">
        {{ $slot }}
    </div>

    @isset($aside)
        <aside class="min-w-0 space-y-6 xl:sticky xl:top-0">
            {{ $aside }}
        </aside>
    @endisset
</div>
