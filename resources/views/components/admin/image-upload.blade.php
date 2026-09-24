@props([
    'name',
    'preset' => 'blog',
    'current' => null,
    'label' => null,
    'removeName' => 'remove_image',
    'help' => null,
    'maxKb' => 2048,
])

@php
    $presets = [
        'blog' => ['label' => 'Featured image', 'aspect' => 'aspect-16/10', 'size' => '1600 × 1000 px', 'ratio' => '16:10'],
        'page' => ['label' => 'Page banner', 'aspect' => 'aspect-8/3', 'size' => '1600 × 600 px', 'ratio' => '8:3'],
        'category' => ['label' => 'Category image', 'aspect' => 'aspect-16/10', 'size' => '1200 × 750 px', 'ratio' => '16:10'],
        'og' => ['label' => 'Social share image', 'aspect' => 'aspect-[40/21]', 'size' => '1200 × 630 px', 'ratio' => '1.91:1'],
        'avatar' => ['label' => 'Avatar', 'aspect' => 'aspect-square', 'size' => '400 × 400 px', 'ratio' => '1:1'],
    ];
    $spec = $presets[$preset] ?? $presets['blog'];
    $config = ['current' => $current, 'maxKb' => (int) $maxKb];
@endphp

<div x-data="imageUpload(@js($config))" {{ $attributes->class('space-y-2') }}>
    <div class="flex flex-wrap items-end justify-between gap-2">
        <x-admin.form.label class="mb-0!">{{ $label ?? $spec['label'] }}</x-admin.form.label>
        <span
            class="inline-flex items-center gap-1.5 rounded-full bg-blue-50 px-2 py-0.5 text-[11px] font-semibold text-blue-700 dark:bg-blue-500/10 dark:text-blue-300"
        >
            <x-admin.icon name="image" class="h-3 w-3" />
            Recommended: {{ $spec['size'] }}
        </span>
    </div>

    <p class="text-xs text-slate-500 dark:text-slate-400">
        {{ $help ?? 'Use a ' . $spec['ratio'] . ' image for the best fit.' }}
        JPG, PNG, WebP or GIF · max {{ round($maxKb / 1024, 1) + 0 }} MB.
    </p>

    <input
        type="file"
        name="{{ $name }}"
        accept="image/jpeg,image/png,image/webp,image/gif"
        class="hidden"
        x-ref="input"
        x-on:change="onFileChange"
    />
    <input type="hidden" name="{{ $removeName }}" :value="removed ? 1 : 0" />

    <div class="max-w-xl">
        <button
            type="button"
            x-on:click="pick()"
            class="{{ $spec['aspect'] }} group relative block w-full cursor-pointer overflow-hidden rounded-xl border border-dashed border-slate-300 bg-slate-50/60 transition-colors hover:border-blue-400 dark:border-slate-700 dark:bg-slate-800/50 dark:hover:border-blue-500/50"
            :class="preview && 'border-solid border-slate-200 dark:border-slate-700'"
            aria-label="Choose {{ strtolower($label ?? $spec['label']) }}"
        >
            <template x-if="preview">
                <img :src="preview" alt="" x-on:error="$el.style.visibility = 'hidden'" class="absolute inset-0 h-full w-full object-cover" />
            </template>

            <template x-if="!preview">
                <span class="absolute inset-0 flex flex-col items-center justify-center gap-1.5 p-4 text-center text-slate-400">
                    <span
                        class="flex h-10 w-10 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-500 shadow-xs dark:border-slate-700 dark:bg-slate-800"
                    >
                        <x-admin.icon name="upload-cloud" class="h-5 w-5" />
                    </span>
                    <span class="text-sm font-semibold text-blue-600 dark:text-blue-400">Click to upload</span>
                    <span class="text-[11px]">{{ $spec['ratio'] }} · {{ $spec['size'] }}</span>
                </span>
            </template>

            <template x-if="preview">
                <span class="absolute inset-0 flex items-center justify-center bg-slate-900/40 opacity-0 transition-opacity group-hover:opacity-100">
                    <span class="rounded-lg bg-white/90 px-3 py-1.5 text-xs font-semibold text-slate-800">Change image</span>
                </span>
            </template>
        </button>
    </div>

    <div class="flex flex-wrap items-center gap-3" x-show="preview" x-cloak>
        <button
            type="button"
            x-on:click="remove()"
            class="inline-flex cursor-pointer items-center gap-1.5 text-xs font-semibold text-red-500 hover:text-red-600"
        >
            <x-admin.icon name="trash" class="h-3.5 w-3.5" />
            Remove image
        </button>
    </div>

    <p
        x-show="error"
        x-cloak
        x-text="error"
        class="rounded-lg bg-red-50 px-3 py-2 text-xs text-red-600 dark:bg-red-500/10 dark:text-red-300"
    ></p>
    <x-admin.form.error :for="$name" />
</div>
