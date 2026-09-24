@props([
    'type',
    'ids' => [],
])

@php
    use App\Http\Controllers\Admin\BulkActionController;
    use Illuminate\Support\Js;

    [$model, $singular, $plural] = BulkActionController::types()[$type];
    $actions = BulkActionController::allowed($type, auth()->user());
    $toTrash = in_array(\Illuminate\Database\Eloquent\SoftDeletes::class, class_uses_recursive($model), true);
    $formId = 'bulk-form-' . $type;
    $modal = 'bulk-delete-' . $type;

    $buttons = [
        'activate' => ['Activate', 'check-circle'],
        'deactivate' => ['Deactivate', 'circle-dashed'],
    ];
    $statuses = [
        'mark-new' => 'New',
        'mark-seen' => 'Seen',
        'mark-pending' => 'Pending',
        'mark-closed' => 'Closed',
    ];
@endphp

<div
    x-data="{
        selected: [],
        pageIds: {{ Js::from(collect($ids)->map(fn ($id) => (int) $id)->values()) }},
        get allSelected() {
            return this.pageIds.length > 0 && this.pageIds.every((id) => this.selected.includes(id))
        },
        get someSelected() {
            return this.selected.length > 0 && ! this.allSelected
        },
        toggleAll() {
            this.selected = this.allSelected ? [] : [...this.pageIds]
        },
        submitBulk(action) {
            const form = document.getElementById({{ Js::from($formId) }})
            form.querySelectorAll('input[name=\'ids[]\']').forEach((input) => input.remove())
            form.querySelector('input[name=action]').value = action
            for (const id of this.selected) {
                const input = document.createElement('input')
                input.type = 'hidden'
                input.name = 'ids[]'
                input.value = id
                form.appendChild(input)
            }
            form.submit()
        },
    }"
    x-on:keydown.escape.window="selected = []"
>
    {{ $slot }}

    @if ($actions)
        <form id="{{ $formId }}" method="POST" action="{{ route('admin.bulk', $type) }}" class="hidden">
            @csrf
            <input type="hidden" name="action" value="" />
        </form>

        <template x-teleport="#admin-portal">
            <div
                x-show="selected.length"
                x-cloak
                x-transition:enter="transition duration-150 ease-out"
                x-transition:enter-start="translate-y-3 opacity-0"
                x-transition:leave="transition duration-100 ease-in"
                x-transition:leave-end="translate-y-3 opacity-0"
                class="pointer-events-none fixed inset-x-0 bottom-5 z-40 flex justify-center px-4"
                role="region"
                aria-label="Actions for selected rows"
            >
                <div
                    class="pointer-events-auto flex max-w-full flex-wrap items-center gap-2 rounded-xl border border-slate-200 bg-white/95 p-2 pl-3 shadow-lg shadow-slate-900/10 backdrop-blur dark:border-slate-700 dark:bg-slate-900/95 dark:shadow-black/40"
                >
                    <span
                        class="tabular text-sm font-semibold whitespace-nowrap text-slate-900 dark:text-white"
                        x-text="selected.length + ' selected'"
                    ></span>
                    <button
                        type="button"
                        x-on:click="selected = []"
                        class="rounded-md px-1.5 py-1 text-xs font-medium text-slate-500 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-white"
                    >
                        Clear
                    </button>
                    <span class="mx-1 h-5 w-px bg-slate-200 dark:bg-slate-700" aria-hidden="true"></span>

                    @foreach ($buttons as $action => [$label, $icon])
                        @if (in_array($action, $actions, true))
                            <x-admin.button
                                type="button"
                                size="sm"
                                variant="secondary"
                                :icon="$icon"
                                x-on:click="submitBulk({{ Js::from($action) }})"
                            >
                                {{ $label }}
                            </x-admin.button>
                        @endif
                    @endforeach

                    @if (array_intersect(array_keys($statuses), $actions))
                        <x-admin.dropdown width="w-44" align="left">
                            <x-slot:trigger>
                                <x-admin.button type="button" size="sm" variant="secondary" icon="circle-dot">
                                    Mark as
                                    <x-slot:rightIcon><x-admin.icon name="chevron-down" class="h-3.5 w-3.5" /></x-slot>
                                </x-admin.button>
                            </x-slot>
                            @foreach ($statuses as $action => $label)
                                @if (in_array($action, $actions, true))
                                    <button
                                        type="button"
                                        x-on:click="submitBulk({{ Js::from($action) }})"
                                        class="flex w-full items-center gap-2 rounded-md px-2.5 py-1.5 text-left text-sm text-slate-700 hover:bg-slate-100 dark:text-slate-200 dark:hover:bg-slate-800"
                                    >
                                        <x-admin.status-badge :status="\Illuminate\Support\Str::after($action, 'mark-')" />
                                    </button>
                                @endif
                            @endforeach
                        </x-admin.dropdown>
                    @endif

                    @if (in_array('delete', $actions, true))
                        <x-admin.button
                            type="button"
                            size="sm"
                            variant="danger-outline"
                            icon="trash"
                            x-on:click="$dispatch('open-modal', {{ Js::from($modal) }})"
                        >
                            {{ $toTrash ? 'Move to Trash' : 'Delete' }}
                        </x-admin.button>
                    @endif
                </div>
            </div>
        </template>

        @if (in_array('delete', $actions, true))
            <x-modal :name="$modal" maxWidth="md">
                <div class="p-6">
                    <div class="flex items-start gap-4">
                        <span
                            class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-red-50 text-red-600 ring-8 ring-red-50/60 dark:bg-red-500/10 dark:text-red-400 dark:ring-red-500/5"
                        >
                            <x-admin.icon name="trash" class="h-5 w-5" />
                        </span>
                        <div class="min-w-0 pt-0.5">
                            <h2 class="text-base font-semibold text-slate-900 dark:text-white">
                                <span
                                    x-text="
                                        selected.length === 1
                                            ? {{ Js::from('Delete 1 ' . $singular . '?') }}
                                            : 'Delete ' + selected.length + ' ' + {{ Js::from($plural) }} + '?'
                                    "
                                ></span>
                            </h2>
                            <p class="mt-1.5 text-sm leading-relaxed text-slate-500 dark:text-slate-400">
                                {{ $toTrash ? 'They are moved to the Trash, where they can be restored.' : 'This cannot be undone.' }}
                            </p>
                        </div>
                    </div>
                    <div class="mt-6 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end" x-data="{ busy: false }">
                        <x-admin.button type="button" variant="secondary" x-on:click="$dispatch('close-modal', {{ Js::from($modal) }})">
                            Cancel
                        </x-admin.button>
                        <x-admin.button type="button" variant="danger" x-bind:disabled="busy" x-on:click="busy = true; submitBulk('delete')">
                            <span x-text="busy ? 'Working…' : {{ Js::from($toTrash ? 'Move to Trash' : 'Delete') }}">
                                {{ $toTrash ? 'Move to Trash' : 'Delete' }}
                            </span>
                        </x-admin.button>
                    </div>
                </div>
            </x-modal>
        @endif
    @endif
</div>
