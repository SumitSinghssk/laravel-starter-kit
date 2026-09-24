@php
    use Illuminate\Support\Number;

    $disk = $extra['disk'] ?? null;
    $brokenLink = collect($extra['links'] ?? [])->first(fn ($link) => ! $link['ok']);
@endphp

<div class="space-y-4 border-t border-slate-100 px-4 py-4 sm:px-5 dark:border-slate-800">
    @if ($disk)
        <div>
            <div class="mb-1.5 flex justify-between text-xs text-slate-500 dark:text-slate-400">
                <span>Disk used</span>
                <span class="tabular">{{ $disk['used_percent'] }}% of {{ Number::fileSize($disk['total'], 1) }}</span>
            </div>
            <div
                class="h-2 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800"
                role="meter"
                aria-label="Disk used"
                aria-valuemin="0"
                aria-valuemax="100"
                aria-valuenow="{{ $disk['used_percent'] }}"
            >
                <div
                    @class([
                        'h-full rounded-full',
                        'bg-red-500' => $disk['used_percent'] >= 95,
                        'bg-amber-500' => $disk['used_percent'] >= 85 && $disk['used_percent'] < 95,
                        'bg-blue-600 dark:bg-blue-500' => $disk['used_percent'] < 85,
                    ])
                    style="width: {{ max(1, min(100, $disk['used_percent'])) }}%"
                ></div>
            </div>
        </div>
    @endif

    <div
        x-data="{
            folders: null,
            failed: false,
            async init() {
                try {
                    const response = await fetch(
                        {{ \Illuminate\Support\Js::from(route('admin.system-health.sizes')) }},
                        { headers: { Accept: 'application/json' } },
                    )
                    if (! response.ok) throw new Error()
                    this.folders = (await response.json()).folders
                } catch {
                    this.failed = true
                }
            },
        }"
    >
        <p class="mb-2 text-xs font-semibold tracking-wide text-slate-400 uppercase">What uses the space</p>
        <p x-show="! folders && ! failed" class="flex items-center gap-2 text-xs text-slate-500 dark:text-slate-400">
            <x-admin.icon name="refresh" class="h-3.5 w-3.5 animate-spin" />
            Measuring folders…
        </p>
        <p x-show="failed" x-cloak class="text-xs text-red-600 dark:text-red-400">Could not measure the folders.</p>
        <dl x-show="folders" x-cloak class="grid grid-cols-2 gap-2">
            <template x-for="(folder, key) in folders" :key="key">
                <div class="rounded-lg border border-slate-100 px-3 py-2 dark:border-slate-800">
                    <dt class="text-xs text-slate-500 dark:text-slate-400" x-text="folder.label"></dt>
                    <dd class="tabular text-sm font-medium text-slate-800 dark:text-slate-100">
                        <span x-text="folder.size"></span>
                        <span x-show="! folder.complete" class="text-xs font-normal text-slate-400">+ (still counting)</span>
                    </dd>
                </div>
            </template>
        </dl>
    </div>

    @if ($brokenLink && $canManage)
        <div class="flex flex-wrap items-center justify-between gap-3 rounded-lg bg-slate-50 px-3 py-2.5 dark:bg-slate-800/60">
            <p class="text-xs text-slate-600 dark:text-slate-300">
                @if ($brokenLink['folder'])
                    public/{{ $brokenLink['link'] }} must be removed by hand first, so nothing inside it is lost by accident.
                @else
                    Create the link so uploaded files show on the site.
                @endif
            </p>
            @unless ($brokenLink['folder'])
                <form method="POST" action="{{ route('admin.system-health.storage-link') }}">
                    @csrf
                    <x-admin.button size="sm" icon="link">Create link</x-admin.button>
                </form>
            @endunless
        </div>
    @endif
</div>
