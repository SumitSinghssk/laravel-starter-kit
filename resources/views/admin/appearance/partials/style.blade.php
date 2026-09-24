@php
    use App\Services\Theme\Theme;
    use Illuminate\Support\Js;

    $card =
        'border-slate-200 hover:border-slate-300 has-checked:border-blue-500! has-checked:bg-blue-50/60 has-checked:ring-1 has-checked:ring-blue-500 has-focus-visible:ring-3 has-focus-visible:ring-blue-500/30 dark:border-slate-700 dark:has-checked:border-blue-400! dark:has-checked:bg-blue-500/10';
@endphp

<div class="space-y-5">
    <x-admin.card title="Corners" text="How rounded buttons, fields and cards are." icon="circle-dashed">
        <div class="grid grid-cols-2 gap-2 sm:grid-cols-3">
            @foreach (Theme::RADII as $key => [$label, $radius])
                <label class="{{ $card }} flex cursor-pointer flex-col items-center gap-3 rounded-xl border p-4 transition">
                    <input
                        type="radio"
                        class="sr-only"
                        value="{{ $key }}"
                        x-model="values.style.radius"
                        x-bind:disabled="! canEdit"
                        @checked($theme['style']['radius'] === $key)
                    />
                    <span class="flex items-center gap-2">
                        <span class="h-8 w-16 bg-blue-600 dark:bg-blue-500" style="border-radius: {{ $radius }}"></span>
                        <span
                            class="h-8 w-8 border-2 border-slate-300 dark:border-slate-600"
                            style="border-radius: min({{ $radius }}, 1.25rem)"
                        ></span>
                    </span>
                    <span class="text-sm font-medium text-slate-800 dark:text-slate-100">{{ $label }}</span>
                </label>
            @endforeach
        </div>
    </x-admin.card>

    <x-admin.card title="Dark mode" text="Some visitors set their phone or computer to dark mode." icon="moon">
        <div class="grid gap-2 sm:grid-cols-2">
            @foreach (['off' => ['Always light', 'The site looks the same for everyone.', 'sun'], 'auto' => ["Follow the visitor's device", 'Dark colours when their device is in dark mode.', 'moon']] as $key => [$label, $text, $icon])
                <label class="{{ $card }} flex cursor-pointer items-start gap-3 rounded-xl border p-4 transition">
                    <input
                        type="radio"
                        class="sr-only"
                        value="{{ $key }}"
                        x-model="values.style.dark_mode"
                        x-bind:disabled="! canEdit"
                        @checked($theme['style']['dark_mode'] === $key)
                    />
                    <span
                        class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300"
                    >
                        <x-admin.icon :name="$icon" class="h-4.5 w-4.5" />
                    </span>
                    <span>
                        <span class="block text-sm font-semibold text-slate-800 dark:text-slate-100">{{ $label }}</span>
                        <span class="block text-xs text-slate-500 dark:text-slate-400">{{ $text }}</span>
                    </span>
                </label>
            @endforeach
        </div>
        <p
            class="mt-3 flex items-center gap-2 text-xs text-slate-500 dark:text-slate-400"
            x-show="values.style.dark_mode === 'auto'"
            @if ($theme['style']['dark_mode'] !== 'auto') x-cloak @endif
        >
            <x-admin.icon name="info" class="h-3.5 w-3.5" />
            <span>
                Choose the dark colours under
                <button
                    type="button"
                    class="cursor-pointer font-semibold text-blue-600 hover:underline dark:text-blue-400"
                    x-on:click="tab = 'slots'"
                >
                    Where colours go
                </button>
                . Anything left on “Same as light” keeps its colour. Use the moon button on the preview to check it.
            </span>
        </p>
    </x-admin.card>
</div>
