@if ($canBackups)
    <div class="border-t border-slate-100 px-4 py-3 sm:px-5 dark:border-slate-800">
        <x-admin.button :href="route('admin.backups.index')" size="sm" variant="secondary" icon="save">Open backups</x-admin.button>
    </div>
@endif
