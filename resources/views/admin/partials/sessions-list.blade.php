@php($deviceIcons = ['desktop' => 'monitor', 'mobile' => 'smartphone', 'tablet' => 'tablet'])

<ul class="divide-y divide-slate-100 dark:divide-slate-800">
    @forelse ($sessions as $session)
        @php($url = $endUrl($session['key']))
        <li class="flex items-center gap-3 py-3 first:pt-0 last:pb-0">
            <span
                @class([
                    'flex h-10 w-10 shrink-0 items-center justify-center rounded-xl',
                    'bg-blue-50 text-blue-600 dark:bg-blue-500/10 dark:text-blue-300' => $session['is_current'],
                    'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400' => ! $session['is_current'],
                ])
            >
                <x-admin.icon :name="$deviceIcons[$session['device']]" class="h-5 w-5" />
            </span>

            <div class="min-w-0 flex-1">
                <p class="flex flex-wrap items-center gap-x-2 gap-y-0.5 text-sm font-medium text-slate-900 dark:text-white">
                    {{ $session['browser'] }} on {{ $session['os'] }}
                    @if ($session['is_current'])
                        <span
                            class="rounded-full bg-blue-50 px-1.5 py-px text-[10px] font-semibold text-blue-700 dark:bg-blue-500/10 dark:text-blue-300"
                        >
                            This device
                        </span>
                    @elseif ($session['online'])
                        <span class="inline-flex items-center gap-1 text-[11px] font-medium text-emerald-600 dark:text-emerald-400">
                            <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                            Active now
                        </span>
                    @endif
                </p>
                <p class="mt-0.5 truncate text-xs text-slate-500 dark:text-slate-400">
                    {{ $session['ip'] ?? 'Unknown IP' }}
                    ·
                    <span title="{{ local_datetime($session['last_active']) }}">
                        {{ $session['is_current'] || $session['online'] ? 'active now' : 'last active ' . $session['last_active']->diffForHumans() }}
                    </span>
                    @if ($session['signed_in_at'])
                        · signed in {{ $session['signed_in_at']->diffForHumans() }}
                    @endif
                </p>
            </div>

            @if ($url && ! $session['is_current'])
                @if ($confirm === 'password')
                    <x-admin.button
                        type="button"
                        size="sm"
                        variant="secondary"
                        icon="log-out"
                        x-on:click="$dispatch('ask-session-password', { action: {{ \Illuminate\Support\Js::from($url) }}, title: {{ \Illuminate\Support\Js::from('Sign out ' . $session['browser'] . ' on ' . $session['os'] . '?') }} })"
                    >
                        Sign out
                    </x-admin.button>
                @else
                    <x-admin.confirm-button
                        :action="$url"
                        method="DELETE"
                        icon="log-out"
                        title="Sign out this device?"
                        :message="$session['browser'] . ' on ' . $session['os'] . ' (' . ($session['ip'] ?? 'unknown IP') . ') is signed out straight away.'"
                        confirm="Sign out"
                    >
                        Sign out
                    </x-admin.confirm-button>
                @endif
            @endif
        </li>
    @empty
        <li class="py-2 text-sm text-slate-500 dark:text-slate-400">Not signed in anywhere right now.</li>
    @endforelse
</ul>
