@php
    $hasErrors = collect($group['checks'])->contains(fn ($check) => $check['key'] === 'app.errors' && $check['status'] !== \App\Services\Health\SystemHealth::OK);
@endphp

@if ($hasErrors && $canLogs)
    <div class="border-t border-slate-100 px-4 py-3 sm:px-5 dark:border-slate-800">
        <x-admin.button :href="route('admin.settings.index', ['tab' => 'logs'])" size="sm" variant="secondary" icon="scroll">
            Open application logs
        </x-admin.button>
    </div>
@endif
