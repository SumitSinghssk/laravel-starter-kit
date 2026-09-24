@props([
    'type',
])

@php
    $manager = app(\App\Services\Trash\TrashManager::class);
    $definition = $manager->types()[$type] ?? null;
    $count =
        $definition &&
        auth()
            ->user()
            ?->can('admin.trash.view') &&
        auth()
            ->user()
            ->can($definition['permission'])
            ? $definition['model']::onlyTrashed()->count()
            : 0;
@endphp

@if ($count)
    <x-admin.button
        variant="ghost"
        icon="trash"
        :href="route('admin.trash.index', ['type' => $type])"
        title="Deleted {{ strtolower($definition['label']) }} you can restore"
    >
        Trash ({{ $count }})
    </x-admin.button>
@endif
