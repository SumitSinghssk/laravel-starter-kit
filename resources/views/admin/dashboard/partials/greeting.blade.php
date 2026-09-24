<div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
    <div>
        <p class="flex items-center gap-1.5 text-xs font-medium text-slate-500 dark:text-slate-400">
            <x-admin.icon name="calendar" class="h-3.5 w-3.5" />
            {{ now()->format('l, j F Y') }}
        </p>
        <h1 class="mt-1 text-2xl font-semibold tracking-tight text-slate-900 dark:text-white">{{ $greeting }}, {{ strtok($user->name, ' ') }}</h1>
        <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">Here's what's happening with your website today.</p>
    </div>
    <div class="flex flex-wrap items-center gap-2">
        @can('admin.pages.create')
            <x-admin.button variant="secondary" :href="route('admin.pages.create')" icon="file-text">New page</x-admin.button>
        @endcan

        @can('admin.blogs.create')
            <x-admin.button :href="route('admin.blogs.create')" icon="plus">New post</x-admin.button>
        @endcan
    </div>
</div>
