@props([
    'name' => null,
    'id' => null,
    'label' => null,
    'value' => null,
    'placeholder' => 'Pick a time',
    'use24Hour' => false,
    'minuteStep' => 1,
    'clearable' => false,
    'description' => null,
    'hint' => null,
    'error' => null,
    'required' => false,
    'disabled' => false,
    'wrapperClass' => '',
])

@php
    use App\Support\FormField;

    $id ??= FormField::id($name) ?: 'time-' . \Illuminate\Support\Str::random(6);
    $message = FormField::error($name, $error, $errors ?? null);
    $current = (string) FormField::old($name, $value);
    $describedBy = $message ? "$id-error" : (filled($hint) ? "$id-hint" : null);
    $boxClasses = 'h-9 w-12 rounded-lg border border-slate-300 bg-white text-center text-sm font-medium text-slate-900 tabular-nums shadow-xs transition focus:border-blue-500 focus:ring-3 focus:ring-blue-500/15 focus:outline-none dark:border-slate-700 dark:bg-slate-900 dark:text-white';
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
        x-data="adminTimePicker({
                    value: @js($current),
                    use24Hour: @js((bool) $use24Hour),
                    minuteStep: @js((int) $minuteStep),
                    disabled: @js((bool) $disabled),
                })"
        x-modelable="value"
        x-on:click.window="outside($event)"
        {{ $attributes->class('relative') }}
    >
        @if ($name)
            <input type="hidden" name="{{ $name }}" value="{{ $current }}" x-bind:value="value" />
        @endif

        <button
            type="button"
            id="{{ $id }}"
            x-ref="trigger"
            x-on:click="open ? hide() : show()"
            x-on:keydown.arrow-down.prevent="show()"
            aria-haspopup="dialog"
            aria-expanded="false"
            x-bind:aria-expanded="open.toString()"
            @if ($message) aria-invalid="true" @endif
            @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif
            @disabled($disabled)
            @class([FormField::controlClasses((bool) $message, $disabled), 'flex items-center gap-2 py-2 pr-2.5 pl-3 text-left', 'cursor-pointer' => ! $disabled])
        >
            <x-admin.icon name="clock" class="h-4 w-4 text-slate-400" />
            <span class="tabular flex-1 truncate" x-text="display || @js($placeholder)" x-bind:class="{ 'text-slate-400': ! display }">
                {{ $current ?: $placeholder }}
            </span>
            <x-admin.icon name="chevron-down" class="h-4 w-4 text-slate-400" />
        </button>

        <template x-teleport="#admin-portal">
            <div
                x-ref="panel"
                x-show="open"
                x-transition.opacity.duration.100ms
                x-bind:style="menuStyle"
                x-on:keydown.escape.prevent.stop="hide()"
                role="dialog"
                aria-modal="false"
                aria-label="{{ $label ?: 'Choose a time' }}"
                class="z-[120] rounded-xl border border-slate-200 bg-white p-3 shadow-lg dark:border-slate-700 dark:bg-slate-900"
            >
                <div class="flex items-center justify-center gap-2">
                    <div class="flex flex-col items-center gap-1">
                        <button
                            type="button"
                            x-on:click="stepHour(1)"
                            class="flex h-6 w-12 cursor-pointer items-center justify-center rounded-md text-slate-400 hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-slate-800 dark:hover:text-slate-200"
                            aria-label="Next hour"
                            tabindex="-1"
                        >
                            <x-admin.icon name="chevron-up" class="h-4 w-4" />
                        </button>
                        <input
                            type="text"
                            inputmode="numeric"
                            maxlength="2"
                            x-ref="hour"
                            x-model="hourText"
                            x-on:focus="
                                editing = 'hour'
                                $el.select()
                            "
                            x-on:blur="commitHour()"
                            x-on:input="hourText = hourText.replace(/\D/g, '')"
                            x-on:keydown.enter.prevent="
                                $el.blur()
                                $refs.minute.focus()
                            "
                            x-on:keydown.arrow-up.prevent="stepHour(1)"
                            x-on:keydown.arrow-down.prevent="stepHour(-1)"
                            x-on:wheel="wheel($event, 'hour')"
                            aria-label="Hour"
                            class="{{ $boxClasses }}"
                        />
                        <button
                            type="button"
                            x-on:click="stepHour(-1)"
                            class="flex h-6 w-12 cursor-pointer items-center justify-center rounded-md text-slate-400 hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-slate-800 dark:hover:text-slate-200"
                            aria-label="Previous hour"
                            tabindex="-1"
                        >
                            <x-admin.icon name="chevron-down" class="h-4 w-4" />
                        </button>
                    </div>

                    <span class="pb-0.5 text-lg font-semibold text-slate-400">:</span>

                    <div class="flex flex-col items-center gap-1">
                        <button
                            type="button"
                            x-on:click="stepMinute(1)"
                            class="flex h-6 w-12 cursor-pointer items-center justify-center rounded-md text-slate-400 hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-slate-800 dark:hover:text-slate-200"
                            aria-label="Next minute"
                            tabindex="-1"
                        >
                            <x-admin.icon name="chevron-up" class="h-4 w-4" />
                        </button>
                        <input
                            type="text"
                            inputmode="numeric"
                            maxlength="2"
                            x-ref="minute"
                            x-model="minuteText"
                            x-on:focus="
                                editing = 'minute'
                                $el.select()
                            "
                            x-on:blur="commitMinute()"
                            x-on:input="minuteText = minuteText.replace(/\D/g, '')"
                            x-on:keydown.enter.prevent="
                                $el.blur()
                                hide()
                            "
                            x-on:keydown.arrow-up.prevent="stepMinute(1)"
                            x-on:keydown.arrow-down.prevent="stepMinute(-1)"
                            x-on:wheel="wheel($event, 'minute')"
                            aria-label="Minute"
                            class="{{ $boxClasses }}"
                        />
                        <button
                            type="button"
                            x-on:click="stepMinute(-1)"
                            class="flex h-6 w-12 cursor-pointer items-center justify-center rounded-md text-slate-400 hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-slate-800 dark:hover:text-slate-200"
                            aria-label="Previous minute"
                            tabindex="-1"
                        >
                            <x-admin.icon name="chevron-down" class="h-4 w-4" />
                        </button>
                    </div>

                    <template x-if="! use24Hour">
                        <div
                            class="ml-1 inline-flex flex-col overflow-hidden rounded-lg border border-slate-200 dark:border-slate-700"
                            role="group"
                            aria-label="AM or PM"
                        >
                            <template x-for="period in ['AM', 'PM']" :key="period">
                                <button
                                    type="button"
                                    x-on:click="setPeriod(period === 'PM')"
                                    x-text="period"
                                    x-bind:aria-pressed="((period === 'PM') === isPm).toString()"
                                    x-bind:class="
                                        (period === 'PM') === isPm
                                            ? 'bg-blue-600 text-white dark:bg-blue-500'
                                            : 'bg-white text-slate-600 hover:bg-slate-50 dark:bg-slate-900 dark:text-slate-300 dark:hover:bg-slate-800'
                                    "
                                    class="h-8 cursor-pointer px-2.5 text-[11px] font-semibold tracking-wide transition-colors"
                                ></button>
                            </template>
                        </div>
                    </template>
                </div>

                <div class="mt-3 flex items-center justify-between gap-2 border-t border-slate-100 pt-2.5 dark:border-slate-800">
                    <button
                        type="button"
                        x-on:click="now()"
                        class="h-7 cursor-pointer rounded-md px-2 text-xs font-medium text-blue-600 hover:bg-blue-50 dark:text-blue-400 dark:hover:bg-blue-500/10"
                    >
                        Now
                    </button>
                    <div class="flex items-center gap-1">
                        @if ($clearable)
                            <button
                                type="button"
                                x-show="value"
                                x-on:click="clear()"
                                class="h-7 cursor-pointer rounded-md px-2 text-xs text-slate-500 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800"
                            >
                                Clear
                            </button>
                        @endif

                        <button
                            type="button"
                            x-on:click="if (! value) set(current.h, current.m); hide()"
                            class="h-7 cursor-pointer rounded-md bg-blue-600 px-2.5 text-xs font-semibold text-white hover:bg-blue-700 dark:bg-blue-500 dark:hover:bg-blue-400"
                        >
                            Done
                        </button>
                    </div>
                </div>
            </div>
        </template>
    </div>
</x-admin.form.field>
