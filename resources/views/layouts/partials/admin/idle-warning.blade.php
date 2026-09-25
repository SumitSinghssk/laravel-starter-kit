@php
    $idleMinutes = (int) \App\Support\SecuritySettings::get('idle_minutes');
@endphp

@if ($idleMinutes > 0)
    <div
        x-data="idleWatcher({
                    minutes: {{ $idleMinutes }},
                    pingUrl: {{ \Illuminate\Support\Js::from(route('admin.session.ping')) }},
                })"
        x-show="warning"
        x-cloak
        x-transition.opacity
        class="fixed inset-0 z-[130] flex items-center justify-center bg-slate-950/50 p-4 backdrop-blur-[2px]"
        role="alertdialog"
        aria-modal="true"
        aria-labelledby="idle-title"
        aria-describedby="idle-text"
    >
        <div class="w-full max-w-sm rounded-2xl border border-slate-200 bg-white p-6 text-center shadow-xl dark:border-slate-800 dark:bg-slate-900">
            <span
                class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-amber-50 text-amber-600 dark:bg-amber-500/10 dark:text-amber-400"
            >
                <x-admin.icon name="clock" class="h-5 w-5" />
            </span>
            <h2 id="idle-title" class="mt-4 text-base font-semibold text-slate-900 dark:text-white">Still there?</h2>
            <p id="idle-text" class="mt-1.5 text-sm text-slate-500 dark:text-slate-400">
                You'll be signed out in
                <span class="tabular font-semibold text-slate-900 dark:text-white" x-text="secondsLeft + ' seconds'">60 seconds</span>
                because there's been no activity for a while.
            </p>
            <div class="mt-5 flex flex-col gap-2">
                <x-admin.button type="button" full x-on:click="stay()" x-effect="warning && $nextTick(() => $el.focus())">
                    Stay signed in
                </x-admin.button>
                <form method="POST" action="{{ route('admin.logout') }}">
                    @csrf
                    <x-admin.button variant="ghost" full>Sign out now</x-admin.button>
                </form>
            </div>
        </div>
    </div>
@endif
