@php
    $dropBase =
        'relative mt-2 flex items-center justify-center overflow-hidden rounded-xl border-2 border-dashed transition focus:outline-none focus-visible:ring-3 focus-visible:ring-blue-500/30';
@endphp

<x-admin.card
    id="settings-branding"
    class="scroll-mt-24"
    title="Branding"
    text="Logos for light and dark backgrounds, and the browser icon."
    icon="palette"
>
    <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
        <div
            x-data="{
                preview:
                    '{{ isset($settings['logo']['light']) ? asset('storage/' . $settings['logo']['light']) : '' }}',
            }"
        >
            <x-admin.form.label>Logo (light mode)</x-admin.form.label>
            <div
                @if ($canUpdate) role="button" tabindex="0" aria-label="Upload image" x-on:click="$refs.lightInput.click()" x-on:keydown.enter.prevent="$refs.lightInput.click()" x-on:keydown.space.prevent="$refs.lightInput.click()" @endif
                class="{{ $dropBase }} {{ $canUpdate ? 'cursor-pointer hover:border-blue-400 hover:bg-blue-50/40' : 'cursor-default' }} h-40 border-slate-300 bg-slate-50"
            >
                <template x-if="preview">
                    <img :src="preview" class="h-full w-full object-contain p-4" alt="Logo light preview" />
                </template>
                <template x-if="!preview">
                    <div class="flex flex-col items-center gap-2 text-center">
                        <span class="flex h-10 w-10 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-400 shadow-xs">
                            <x-admin.icon name="upload-cloud" class="h-5 w-5" />
                        </span>
                        @if ($canUpdate)
                            <span class="text-xs text-slate-500">
                                <span class="font-medium text-blue-600">Click to upload</span>
                                · PNG, SVG or WebP
                            </span>
                        @else
                            <span class="text-xs text-slate-500">No logo uploaded</span>
                        @endif
                    </div>
                </template>
                @if ($canUpdate)
                    <input
                        type="file"
                        name="logo_light"
                        x-ref="lightInput"
                        class="hidden"
                        accept="image/*"
                        x-on:change="preview = URL.createObjectURL($event.target.files[0])"
                    />
                @endif
            </div>
            <p class="mt-1.5 text-xs text-slate-500 dark:text-slate-400">Shown on light backgrounds.</p>
            <x-admin.form.error for="logo_light" />
        </div>

        <div
            x-data="{
                preview:
                    '{{ isset($settings['logo']['dark']) ? asset('storage/' . $settings['logo']['dark']) : '' }}',
            }"
        >
            <x-admin.form.label>Logo (dark mode)</x-admin.form.label>
            <div
                @if ($canUpdate) role="button" tabindex="0" aria-label="Upload image" x-on:click="$refs.darkInput.click()" x-on:keydown.enter.prevent="$refs.darkInput.click()" x-on:keydown.space.prevent="$refs.darkInput.click()" @endif
                class="{{ $dropBase }} {{ $canUpdate ? 'cursor-pointer hover:border-blue-400 hover:bg-slate-950' : 'cursor-default' }} h-40 border-slate-700 bg-slate-900 dark:bg-slate-950"
            >
                <template x-if="preview">
                    <img :src="preview" class="h-full w-full object-contain p-4" alt="Logo dark preview" />
                </template>
                <template x-if="!preview">
                    <div class="flex flex-col items-center gap-2 text-center">
                        <span class="flex h-10 w-10 items-center justify-center rounded-lg border border-slate-700 bg-slate-800 text-slate-400">
                            <x-admin.icon name="upload-cloud" class="h-5 w-5" />
                        </span>
                        @if ($canUpdate)
                            <span class="text-xs text-slate-400">
                                <span class="font-medium text-blue-300">Click to upload</span>
                                · PNG, SVG or WebP
                            </span>
                        @else
                            <span class="text-xs text-slate-400">No logo uploaded</span>
                        @endif
                    </div>
                </template>
                @if ($canUpdate)
                    <input
                        type="file"
                        name="logo_dark"
                        x-ref="darkInput"
                        class="hidden"
                        accept="image/*"
                        x-on:change="preview = URL.createObjectURL($event.target.files[0])"
                    />
                @endif
            </div>
            <p class="mt-1.5 text-xs text-slate-500 dark:text-slate-400">Shown on dark backgrounds.</p>
            <x-admin.form.error for="logo_dark" />
        </div>
    </div>

    <div
        x-data="{
            preview:
                '{{ isset($settings['favicon']) ? asset('storage/' . $settings['favicon']) : '' }}',
        }"
        class="mt-5 border-t border-slate-100 pt-5 dark:border-slate-800"
    >
        <x-admin.form.label>Favicon</x-admin.form.label>
        <div class="mt-2 flex items-center gap-4">
            <div
                @if ($canUpdate) role="button" tabindex="0" aria-label="Upload image" x-on:click="$refs.faviconInput.click()" x-on:keydown.enter.prevent="$refs.faviconInput.click()" x-on:keydown.space.prevent="$refs.faviconInput.click()" @endif
                class="{{ $canUpdate ? 'cursor-pointer hover:border-blue-400 hover:bg-blue-50/40 dark:hover:bg-slate-800' : 'cursor-default' }} flex h-16 w-16 shrink-0 items-center justify-center overflow-hidden rounded-xl border-2 border-dashed border-slate-300 bg-slate-50 text-slate-400 transition focus:outline-none focus-visible:ring-3 focus-visible:ring-blue-500/30 dark:border-slate-700 dark:bg-slate-800/60"
            >
                <img x-show="preview" :src="preview" class="h-full w-full object-cover p-1" alt="Favicon preview" />
                <x-admin.icon name="image" x-show="!preview" class="h-5 w-5" />
                @if ($canUpdate)
                    <input
                        type="file"
                        name="favicon"
                        x-ref="faviconInput"
                        class="hidden"
                        accept="image/*"
                        x-on:change="preview = URL.createObjectURL($event.target.files[0])"
                    />
                @endif
            </div>
            <div class="min-w-0">
                <p class="text-sm font-medium text-slate-800 dark:text-slate-200">Browser icon</p>
                <p class="text-xs text-slate-500 dark:text-slate-400">Square PNG or ICO (32×32 px recommended). Click the tile to change it.</p>
            </div>
        </div>
        <x-admin.form.error for="favicon" />
    </div>
</x-admin.card>
