<div class="space-y-4 border-t border-slate-100 px-4 py-4 sm:px-5 dark:border-slate-800">
    @unless ($extra['running'])
        <div>
            <p class="mb-2 text-xs text-slate-500 dark:text-slate-400">
                {{ $extra['windows'] ? 'Run this once in a Command Prompt opened as administrator:' : 'Add this line to the server\'s crontab (crontab -e):' }}
            </p>
            <div x-data="{ copied: false }" class="relative">
                <code
                    class="block rounded-lg bg-slate-100 p-2.5 pr-9 font-mono text-[11px] break-all text-slate-700 dark:bg-slate-800 dark:text-slate-300"
                >
                    {{ $extra['command'] }}
                </code>
                <button
                    type="button"
                    x-on:click="
                        window
                            .copyText({{ \Illuminate\Support\Js::from($extra['command']) }})
                            .then((ok) => {
                                copied = ok
                                setTimeout(() => (copied = false), 1500)
                            })
                    "
                    class="absolute top-1.5 right-1.5 cursor-pointer rounded-md p-1 text-slate-400 hover:bg-white hover:text-slate-700 dark:hover:bg-slate-900"
                    aria-label="Copy command"
                    title="Copy"
                >
                    <x-admin.icon name="copy" class="h-3.5 w-3.5" x-show="! copied" />
                    <x-admin.icon name="check" class="h-3.5 w-3.5 text-emerald-500" x-show="copied" x-cloak />
                </button>
            </div>
        </div>
    @endunless

    @if ($extra['tasks'])
        <div>
            <p class="mb-2 text-xs font-semibold tracking-wide text-slate-400 uppercase">Scheduled tasks</p>
            <ul class="divide-y divide-slate-100 rounded-lg border border-slate-100 dark:divide-slate-800 dark:border-slate-800">
                @foreach ($extra['tasks'] as $task)
                    <li class="flex flex-wrap items-center justify-between gap-x-3 gap-y-0.5 px-3 py-2">
                        <span class="font-mono text-xs text-slate-700 dark:text-slate-200">{{ $task['name'] }}</span>
                        <span class="text-xs text-slate-500 dark:text-slate-400">
                            {{ $cron($task['expression']) }}
                            @if ($task['next'])
                                <span class="text-slate-400">· next {{ $task['next']->diffForHumans() }}</span>
                            @endif
                        </span>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
</div>
