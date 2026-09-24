@can('admin.blogs.view')
    <x-admin.card title="Latest posts" text="Recently written or edited articles." icon="newspaper" :padded="false">
        <x-slot:actions>
            <x-admin.button variant="ghost" size="sm" :href="route('admin.blogs.index')">
                Manage posts
                <x-slot:rightIcon>
                    <x-admin.icon name="arrow-right" class="h-3.5 w-3.5" />
                </x-slot>
            </x-admin.button>
        </x-slot>

        <div class="grid grid-cols-1 gap-px bg-slate-100 sm:grid-cols-2 lg:grid-cols-5 dark:bg-slate-800">
            @forelse ($recentPosts as $post)
                @php
                    $isLive = $post->status === \App\Enums\CommonStatusEnum::ACTIVE && (! $post->published_at || $post->published_at->lte(now()));
                    $isScheduled = $post->status === \App\Enums\CommonStatusEnum::ACTIVE && $post->published_at?->isFuture();
                @endphp

                <a
                    href="{{ route('admin.blogs.edit', $post) }}"
                    class="group flex gap-3 bg-white p-4 transition hover:bg-slate-50 lg:flex-col dark:bg-slate-900 dark:hover:bg-slate-800/60"
                >
                    <x-admin.thumb
                        :src="$post->featured_image ? asset('storage/' . $post->featured_image) : null"
                        class="aspect-16/10 w-24 rounded-lg lg:w-full"
                    />
                    <div class="min-w-0">
                        <p
                            class="line-clamp-2 text-sm font-medium text-slate-900 group-hover:text-blue-700 dark:text-white dark:group-hover:text-blue-300"
                        >
                            {{ $post->title }}
                        </p>
                        <p class="mt-1.5 flex items-center gap-2 text-xs text-slate-400">
                            <x-admin.status-badge
                                :tone="$isLive ? 'success' : ($isScheduled ? 'warning' : 'neutral')"
                                :label="$isLive ? 'Live' : ($isScheduled ? 'Scheduled' : 'Draft')"
                            />
                            {{ $post->updated_at->diffForHumans(null, true) }} ago
                        </p>
                    </div>
                </a>
            @empty
                <div class="col-span-full bg-white px-4 py-12 text-center dark:bg-slate-900">
                    <p class="text-sm font-semibold text-slate-700 dark:text-slate-200">No posts yet</p>
                    @can('admin.blogs.create')
                        <a href="{{ route('admin.blogs.create') }}" class="mt-2 inline-flex items-center gap-1 text-xs font-semibold text-blue-600">
                            Write your first post
                            <x-admin.icon name="arrow-right" class="h-3 w-3" />
                        </a>
                    @endcan
                </div>
            @endforelse
        </div>
    </x-admin.card>
@endcan
