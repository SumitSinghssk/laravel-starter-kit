@php
    $hasPages = $paginator->hasPages();
    $hasTotals = method_exists($paginator, 'total') && method_exists($paginator, 'firstItem');

    $btnBase = 'tabular inline-flex h-8 min-w-8 items-center justify-center rounded-lg px-2 text-xs font-semibold transition-colors';
    $btnIdle = 'text-slate-600 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-white';
    $btnActive = 'bg-slate-100 text-slate-900 ring-1 ring-slate-200 ring-inset dark:bg-slate-800 dark:text-white dark:ring-slate-700';
    $navBase = 'inline-flex h-8 items-center gap-1 rounded-lg border px-2.5 text-xs font-semibold shadow-xs transition-colors';
    $navIdle = 'border-slate-200 bg-white text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700';
    $navDisabled = 'cursor-not-allowed border-slate-100 bg-white text-slate-300 shadow-none dark:border-slate-800 dark:bg-slate-900 dark:text-slate-600';
@endphp

<nav role="navigation" aria-label="{{ __('Pagination Navigation') }}" class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
    <p class="text-xs text-slate-500 dark:text-slate-400">
        @if ($hasTotals && $paginator->total() > 0)
            Showing
            <span class="tabular font-semibold text-slate-700 dark:text-slate-200">{{ $paginator->firstItem() }}–{{ $paginator->lastItem() }}</span>
            of
            <span class="tabular font-semibold text-slate-700 dark:text-slate-200">{{ number_format($paginator->total()) }}</span>
        @endif
    </p>

    @if ($hasPages)
        <div class="flex items-center gap-1">
            @if ($paginator->onFirstPage())
                <span aria-disabled="true" aria-label="{{ __('pagination.previous') }}" class="{{ $navBase }} {{ $navDisabled }}">
                    <x-admin.icon name="arrow-left" class="h-3.5 w-3.5" />
                    <span class="hidden sm:inline">Previous</span>
                </span>
            @else
                <a
                    href="{{ $paginator->previousPageUrl() }}"
                    rel="prev"
                    aria-label="{{ __('pagination.previous') }}"
                    class="{{ $navBase }} {{ $navIdle }}"
                >
                    <x-admin.icon name="arrow-left" class="h-3.5 w-3.5" />
                    <span class="hidden sm:inline">Previous</span>
                </a>
            @endif

            <div class="mx-1 hidden items-center gap-0.5 sm:flex">
                @foreach ($elements as $element)
                    @if (is_string($element))
                        <span aria-disabled="true" class="px-1.5 text-xs font-medium text-slate-400 dark:text-slate-600">{{ $element }}</span>
                    @endif

                    @if (is_array($element))
                        @foreach ($element as $page => $url)
                            @if ($page == $paginator->currentPage())
                                <span aria-current="page" class="{{ $btnBase }} {{ $btnActive }}">{{ $page }}</span>
                            @else
                                <a
                                    href="{{ $url }}"
                                    aria-label="{{ __('Go to page :page', ['page' => $page]) }}"
                                    class="{{ $btnBase }} {{ $btnIdle }}"
                                >
                                    {{ $page }}
                                </a>
                            @endif
                        @endforeach
                    @endif
                @endforeach
            </div>

            @if ($hasTotals)
                <span class="tabular px-2 text-xs font-medium text-slate-500 sm:hidden dark:text-slate-400">
                    {{ $paginator->currentPage() }} / {{ $paginator->lastPage() }}
                </span>
            @endif

            @if ($paginator->hasMorePages())
                <a
                    href="{{ $paginator->nextPageUrl() }}"
                    rel="next"
                    aria-label="{{ __('pagination.next') }}"
                    class="{{ $navBase }} {{ $navIdle }}"
                >
                    <span class="hidden sm:inline">Next</span>
                    <x-admin.icon name="arrow-right" class="h-3.5 w-3.5" />
                </a>
            @else
                <span aria-disabled="true" aria-label="{{ __('pagination.next') }}" class="{{ $navBase }} {{ $navDisabled }}">
                    <span class="hidden sm:inline">Next</span>
                    <x-admin.icon name="arrow-right" class="h-3.5 w-3.5" />
                </span>
            @endif
        </div>
    @endif
</nav>
