@props([
    'name' => null,
    'id' => null,
    'label' => null,
    'options' => [],
    'value' => [],
    'placeholder' => 'Select…',
    'emptyMessage' => 'No options',
    'max' => null,
    'description' => null,
    'hint' => null,
    'error' => null,
    'required' => false,
    'disabled' => false,
    'wrapperClass' => '',
])

@php
    use App\Support\FormField;

    $id ??= FormField::id($name) ?: 'multi-select-' . \Illuminate\Support\Str::random(6);
    $message = FormField::error($name, $error, $errors ?? null);
    $list = FormField::options($options);
    $selected = FormField::values(FormField::old($name, $value));
    $inputName = $name ? rtrim($name, '[]') . '[]' : null;
    $describedBy = $message ? "$id-error" : (filled($hint) ? "$id-hint" : null);
@endphp

<x-admin.form.field
    :label="$label"
    :for="$id"
    :required="$required"
    :description="$description"
    :hint="$hint"
    :error="$message"
    :class="$wrapperClass"
>
    <div
        x-data="adminMultiSelect({
                    options: @js($list),
                    value: @js($selected),
                    disabled: @js($disabled),
                    max: @js($max),
                })"
        x-modelable="value"
        x-on:click.window="outside($event)"
        {{ $attributes->class('relative') }}
    >
        @if ($inputName)
            <template x-for="item in value" :key="item">
                <input type="hidden" name="{{ $inputName }}" x-bind:value="item" />
            </template>
        @endif

        <div
            x-ref="trigger"
            x-on:click="
                $refs.search.focus()
                show()
            "
            @class([FormField::controlClasses((bool) $message, $disabled), 'flex min-h-[38px] flex-wrap items-center gap-1.5 py-1.5 pr-8 pl-2', 'cursor-text' => ! $disabled])
            x-bind:class="
                open &&
                    '{{ $message ? 'border-red-400 ring-3 ring-red-500/15' : 'border-blue-400 ring-3 ring-blue-500/15' }}'
            "
        >
            <template x-for="item in value" :key="item">
                <span
                    class="inline-flex max-w-full items-center gap-1 rounded-md bg-blue-50 py-0.5 pr-1 pl-2 text-xs font-semibold text-blue-700 dark:bg-blue-500/15 dark:text-blue-300"
                >
                    <span class="truncate" x-text="labelOf(item)"></span>
                    @unless ($disabled)
                        <button
                            type="button"
                            x-on:click.stop="remove(item)"
                            x-bind:aria-label="'Remove ' + labelOf(item)"
                            class="flex h-4 w-4 cursor-pointer items-center justify-center rounded hover:bg-blue-200/60 dark:hover:bg-blue-400/20"
                        >
                            <x-admin.icon name="x" stroke="2.5" class="h-2.5 w-2.5" />
                        </button>
                    @endunless
                </span>
            </template>

            <input
                x-ref="search"
                id="{{ $id }}"
                x-model="query"
                x-on:input="
                    show()
                    active = 0
                "
                x-on:keydown="key($event)"
                type="text"
                role="combobox"
                aria-autocomplete="list"
                aria-controls="{{ $id }}-listbox"
                aria-expanded="false"
                x-bind:aria-expanded="open.toString()"
                x-bind:aria-activedescendant="open && filtered.length ? @js($id . '-option-') + active : null"
                x-bind:placeholder="value.length ? '' : @js($placeholder)"
                placeholder="{{ $selected ? '' : $placeholder }}"
                @if ($message) aria-invalid="true" @endif
                @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif
                @disabled($disabled)
                autocomplete="off"
                class="min-w-24 flex-1 border-0 bg-transparent p-1 text-sm text-slate-900 placeholder:text-slate-400 focus:ring-0 focus:outline-none disabled:cursor-not-allowed dark:text-white"
            />

            <x-admin.icon
                name="chevron-down"
                class="pointer-events-none absolute top-1/2 right-2.5 h-4 w-4 -translate-y-1/2 text-slate-400 transition-transform"
                x-bind:class="open && 'rotate-180'"
            />
        </div>

        <template x-teleport="#admin-portal">
            <div
                x-ref="panel"
                x-show="open"
                x-transition.opacity.duration.100ms
                x-bind:style="menuStyle"
                class="z-[120] flex max-h-72 flex-col overflow-hidden rounded-lg border border-slate-200 bg-white shadow-lg shadow-slate-900/10 dark:border-slate-700 dark:bg-slate-900"
            >
                <div
                    id="{{ $id }}-listbox"
                    role="listbox"
                    aria-multiselectable="true"
                    @if (filled($label)) aria-label="{{ $label }}" @endif
                    class="custom-scrollbar overflow-y-auto py-1"
                >
                    <template x-for="(option, index) in filtered" :key="option.value">
                        <div role="presentation">
                            <div
                                x-show="option.group && option.group !== filtered[index - 1]?.group"
                                x-text="option.group"
                                role="presentation"
                                class="px-3 pt-2 pb-1 text-[10px] font-bold tracking-wider text-slate-400 uppercase select-none"
                            ></div>
                            <div
                                role="option"
                                x-bind:id="@js($id . '-option-') + index"
                                x-bind:data-index="index"
                                x-bind:aria-selected="isSelected(option.value).toString()"
                                x-on:mousedown.prevent
                                x-on:click="toggleOption(option)"
                                x-on:mousemove="active = index"
                                x-bind:class="active === index && 'bg-slate-100 dark:bg-slate-800'"
                                x-bind:style="option.depth ? `padding-left: ${0.75 + option.depth * 1.1}rem` : ''"
                                class="flex cursor-pointer items-center gap-2.5 px-3 py-2 text-sm text-slate-700 dark:text-slate-200"
                            >
                                <span
                                    x-bind:class="
                                        isSelected(option.value)
                                            ? 'border-blue-600 bg-blue-600 text-white dark:border-blue-500 dark:bg-blue-500'
                                            : 'border-slate-300 bg-white text-transparent dark:border-slate-600 dark:bg-slate-800'
                                    "
                                    class="flex h-4 w-4 shrink-0 items-center justify-center rounded border transition-colors"
                                    aria-hidden="true"
                                >
                                    <x-admin.icon name="check" stroke="3" class="h-3 w-3" />
                                </span>
                                <span class="truncate" x-bind:class="option.depth === 0 && 'font-semibold'" x-text="option.label"></span>
                            </div>
                        </div>
                    </template>

                    <p x-show="! filtered.length" class="px-3 py-2 text-sm text-slate-500 dark:text-slate-400">
                        <span x-text="query.trim() ? 'No matches' : @js($emptyMessage)"></span>
                    </p>
                </div>
            </div>
        </template>
    </div>
</x-admin.form.field>
