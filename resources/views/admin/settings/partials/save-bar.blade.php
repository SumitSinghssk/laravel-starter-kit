<div
    class="sticky bottom-3 z-20 flex items-center justify-between gap-3 rounded-xl border border-slate-200/80 bg-white/90 px-4 py-3 shadow-sm backdrop-blur-md dark:border-slate-800 dark:bg-slate-900/90"
>
    <p class="hidden min-w-0 items-center gap-1.5 text-xs text-slate-500 sm:flex dark:text-slate-400">
        <x-admin.icon name="info" class="h-3.5 w-3.5" />
        <span class="truncate">{{ $note ?? 'Changes take effect on the website as soon as you save.' }}</span>
    </p>

    <div class="flex w-full items-center justify-end sm:w-auto">
        <x-admin.button icon="save">
            <span x-text="submitting ? 'Saving…' : @js($label)">{{ $label }}</span>
        </x-admin.button>
    </div>
</div>
