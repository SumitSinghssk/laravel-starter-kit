@props([
    'tabs' => [],
    'label' => 'Sections',
])

<nav
    aria-label="{{ $label }}"
    {{ $attributes->class('scrollbar-hide mb-4 flex gap-1 overflow-x-auto shadow-[inset_0_-1px_0] shadow-slate-200 dark:shadow-slate-800') }}
>
    @foreach ($tabs as $tab)
        <a
            href="{{ $tab['url'] }}"
            @if ($tab['active'] ?? false) aria-current="page" @endif
            @class([
                'inline-flex shrink-0 items-center gap-2 border-b-2 px-3 py-2.5 text-sm font-medium whitespace-nowrap transition focus:outline-none focus-visible:bg-slate-100 dark:focus-visible:bg-slate-800',
                'border-blue-600 text-blue-700 dark:border-blue-400 dark:text-blue-300' => $tab['active'] ?? false,
                'border-transparent text-slate-500 hover:border-slate-300 hover:text-slate-900 dark:text-slate-400 dark:hover:border-slate-600 dark:hover:text-white' => ! (
                    $tab['active'] ?? false
                ),
            ])
        >
            @if (! empty($tab['icon']))
                <x-admin.icon :name="$tab['icon']" class="h-4 w-4" />
            @endif

            {{ $tab['label'] }}
            @isset($tab['count'])
                <span
                    @class([
                        'tabular rounded-full px-1.5 py-px text-[11px] font-semibold',
                        'bg-blue-50 text-blue-700 dark:bg-blue-500/10 dark:text-blue-300' => $tab['active'] ?? false,
                        'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300' => ! ($tab['active'] ?? false),
                    ])
                >
                    {{ $tab['count'] }}
                </span>
            @endisset
        </a>
    @endforeach
</nav>
