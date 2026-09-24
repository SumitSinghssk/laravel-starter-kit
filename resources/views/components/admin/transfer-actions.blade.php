@props([
    'type',
])

@php
    $transfer = app(\App\Services\Transfer\TransferRegistry::class)->all()[$type] ?? null;
    $user = auth()->user();
    $canExport = $transfer && $user?->can($transfer->viewPermission());
    $canImport = $transfer && $user && $transfer->canImport($user);
@endphp

@if ($canImport)
    <x-admin.button variant="secondary" icon="upload" :href="route('admin.transfer.create', $type)">Import</x-admin.button>
@endif

@if ($canExport)
    <x-admin.button
        variant="secondary"
        icon="download"
        :href="route('admin.transfer.export', $type)"
        title="Download all {{ strtolower($transfer->label()) }} as a CSV file (opens in Excel)"
    >
        Export
    </x-admin.button>
@endif
