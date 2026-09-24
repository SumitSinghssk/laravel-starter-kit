@php
    $weightNames = [100 => 'Thin', 200 => 'Extra light', 300 => 'Light', 400 => 'Regular', 500 => 'Medium', 600 => 'Semibold', 700 => 'Bold', 800 => 'Extra bold', 900 => 'Black'];
@endphp

<form
    method="POST"
    action="{{ $action }}"
    enctype="multipart/form-data"
    class="space-y-4"
    x-data="{
        submitting: false,
        rows: [],
        guess(name) {
            const n = name.toLowerCase().replace(/[\s_]+/g, '-')
            const table = [
                [/(hairline|thin)/, 100],
                [/(extra|ultra)-?light/, 200],
                [/light/, 300],
                [/medium/, 500],
                [/(semi|demi)-?bold/, 600],
                [/(extra|ultra)-?bold/, 800],
                [/(black|heavy)/, 900],
                [/bold/, 700],
            ]
            const found = table.find(([pattern]) => pattern.test(n))
            return {
                weight: found ? found[1] : 400,
                style: /(italic|oblique)/.test(n) ? 'italic' : 'normal',
            }
        },
        picked(event) {
            this.rows = [...event.target.files].map((file) => ({
                name: file.name,
                size: file.size,
                ...this.guess(file.name),
            }))
        },
        remove(index) {
            const transfer = new DataTransfer()
            ;[...this.$refs.files.files].forEach(
                (file, i) => i !== index && transfer.items.add(file),
            )
            this.$refs.files.files = transfer.files
            this.rows.splice(index, 1)
        },
        get duplicates() {
            const seen = new Set()
            return this.rows.some((row) => {
                const key = row.weight + row.style
                return seen.has(key) || ! seen.add(key)
            })
        },
    }"
    x-on:submit="submitting = true"
>
    @csrf

    @if ($withFamily)
        <div class="grid gap-4 sm:grid-cols-2">
            <x-admin.form.input name="family" label="Font name" placeholder="e.g. Gilroy" hint="The name you'll see in the lists." required />
            <x-admin.form.select name="fallback" label="If it can't load, use" :options="$fallbacks" :value="old('fallback', 'sans-serif')" />
        </div>
    @endif

    <label
        class="flex cursor-pointer flex-col items-center justify-center gap-1.5 rounded-xl border border-dashed border-slate-300 bg-white px-4 py-6 text-center transition hover:border-blue-400 dark:border-slate-700 dark:bg-slate-900"
    >
        <input type="file" name="files[]" multiple accept=".woff2,.woff,.ttf,.otf" class="sr-only" x-ref="files" x-on:change="picked($event)" />
        <x-admin.icon name="upload-cloud" class="h-5 w-5 text-slate-400" />
        <span class="text-sm">
            <span class="font-semibold text-blue-600 dark:text-blue-400">Choose font files</span>
            <span class="text-slate-500 dark:text-slate-400">· WOFF2, WOFF, TTF or OTF, up to 5 MB each</span>
        </span>
    </label>

    <template x-if="rows.length">
        <ul
            class="divide-y divide-slate-100 rounded-xl border border-slate-200 bg-white dark:divide-slate-800 dark:border-slate-700 dark:bg-slate-900"
        >
            <template x-for="(row, index) in rows" :key="row.name + index">
                <li class="flex flex-wrap items-center gap-2 px-3 py-2">
                    <span class="min-w-0 flex-1 truncate font-mono text-xs text-slate-700 dark:text-slate-200" x-text="row.name"></span>
                    <select
                        name="weights[]"
                        x-model.number="row.weight"
                        class="h-8 rounded-lg border border-slate-200 bg-white px-2 text-xs dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                        aria-label="Weight"
                    >
                        @foreach ($weightNames as $weight => $name)
                            <option value="{{ $weight }}">{{ $weight }} · {{ $name }}</option>
                        @endforeach
                    </select>
                    <select
                        name="styles[]"
                        x-model="row.style"
                        class="h-8 rounded-lg border border-slate-200 bg-white px-2 text-xs dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                        aria-label="Style"
                    >
                        <option value="normal">Normal</option>
                        <option value="italic">Italic</option>
                    </select>
                    <button
                        type="button"
                        x-on:click="remove(index)"
                        class="flex h-8 w-8 cursor-pointer items-center justify-center rounded-md text-slate-400 hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-500/10"
                        aria-label="Remove file"
                    >
                        <x-admin.icon name="x" class="h-4 w-4" />
                    </button>
                </li>
            </template>
        </ul>
    </template>

    <p class="text-xs text-amber-700 dark:text-amber-300" x-show="duplicates" x-cloak>
        Two files have the same weight and style. Give each its own.
    </p>
    <x-admin.form.error for="files" />
    @foreach ($errors->getMessages() as $key => $messages)
        @if (str_starts_with($key, 'files.') || str_starts_with($key, 'weights') || str_starts_with($key, 'styles'))
            <x-admin.form.error :message="$messages[0]" />

            @break
        @endif
    @endforeach

    <div class="flex justify-end">
        <x-admin.button icon="upload" x-bind:disabled="submitting || ! rows.length || duplicates">
            <span x-text="submitting ? 'Uploading…' : {{ \Illuminate\Support\Js::from($submit) }}">{{ $submit }}</span>
        </x-admin.button>
    </div>
</form>
