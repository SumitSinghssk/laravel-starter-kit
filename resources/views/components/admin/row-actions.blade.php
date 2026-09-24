@props([
    'size' => 'lg',

    'viewRoute' => null,
    'viewClick' => null,
    'canView' => true,
    'viewNewTab' => null,

    'editRoute' => null,
    'canEdit' => true,

    'deleteRoute' => null,
    'deleteId' => null,
    'deleteModalName' => null,

    'deleteFormAction' => null,
    'deleteFormField' => null,
    'deleteConfirmMessage' => 'Are you sure? This cannot be undone.',
    'deleteTitle' => null,
    'deleteMessage' => null,

    'canDelete' => true,
])

@php
    $isSmall = $size === 'sm';
    $base = $isSmall
        ? 'inline-flex h-7.5 w-7.5 cursor-pointer items-center justify-center rounded-lg text-slate-400 transition-colors focus:outline-none focus-visible:ring-3 focus-visible:ring-blue-500/30'
        : 'inline-flex h-8.5 cursor-pointer items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 text-xs font-semibold text-slate-700 shadow-xs transition-colors hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700';

    $viewStyles = $isSmall ? "$base hover:bg-slate-100 hover:text-slate-900 dark:hover:bg-slate-800 dark:hover:text-white" : $base;
    $editStyles = $isSmall ? "$base hover:bg-blue-50 hover:text-blue-600 dark:hover:bg-blue-500/10 dark:hover:text-blue-400" : $base;
    $deleteStyles = $isSmall ? "$base hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-500/10 dark:hover:text-red-400" : "$base text-red-600 dark:text-red-400";

    $viewNewTab ??= $viewRoute && ! str_starts_with($viewRoute, url('admin'));
@endphp

<div class="flex items-center justify-end gap-0.5">
    @if ($viewRoute && $canView)
        <x-admin.tooltip :text="$viewNewTab ? 'View on site' : 'View'">
            <a
                href="{{ $viewRoute }}"
                class="{{ $viewStyles }}"
                @if ($viewNewTab) target="_blank" rel="noopener" @endif
                @if ($isSmall) aria-label="{{ $viewNewTab ? 'View on site' : 'View' }}" @endif
            >
                <x-admin.icon :name="$viewNewTab ? 'external-link' : 'eye'" class="h-4 w-4" />
                @if (! $isSmall)
                    <span>View</span>
                @endif
            </a>
        </x-admin.tooltip>
    @endif

    @if ($viewClick && $canView)
        <x-admin.tooltip text="View">
            <button type="button" x-on:click="{{ $viewClick }}" class="{{ $viewStyles }}" @if ($isSmall) aria-label="View" @endif>
                <x-admin.icon name="eye" class="h-4 w-4" />
                @if (! $isSmall)
                    <span>View</span>
                @endif
            </button>
        </x-admin.tooltip>
    @endif

    @if ($editRoute && $canEdit)
        <x-admin.tooltip text="Edit">
            <a href="{{ $editRoute }}" class="{{ $editStyles }}" @if ($isSmall) aria-label="Edit" @endif>
                <x-admin.icon name="pencil" class="h-4 w-4" />
                @if (! $isSmall)
                    <span>Edit</span>
                @endif
            </a>
        </x-admin.tooltip>
    @endif

    @if ($deleteRoute && $canDelete)
        <x-admin.delete-button
            :route="$deleteRoute"
            :id="$deleteId"
            :modalName="$deleteModalName"
            :size="$size"
            :title="$deleteTitle ?? 'Delete this item?'"
            :message="$deleteMessage ?? 'It will be permanently removed. This action cannot be undone.'"
        />
    @endif

    @if ($deleteFormAction && $canDelete)
        <x-admin.tooltip text="Delete">
            <form
                method="POST"
                action="{{ $deleteFormAction }}"
                x-data
                x-on:submit.prevent="if (confirm(@js($deleteConfirmMessage))) $el.submit()"
            >
                @csrf
                @method('DELETE')
                @if ($deleteFormField)
                    <input type="hidden" name="{{ $deleteFormField['name'] }}" value="{{ $deleteFormField['value'] }}" />
                @endif

                <button type="submit" class="{{ $deleteStyles }}" @if ($isSmall) aria-label="Delete" @endif>
                    <x-admin.icon name="trash" class="h-4 w-4" />
                    @if (! $isSmall)
                        <span>Delete</span>
                    @endif
                </button>
            </form>
        </x-admin.tooltip>
    @endif
</div>
