<form method="POST" action="{{ route('admin.settings.robots.update') }}" x-data="{ submitting: false }" x-on:submit="submitting = true">
    @csrf

    <x-admin.card title="Robots.txt" text="Tells search engines which pages they may crawl." icon="bot">
        @can('admin.settings.robots.update')
            <x-slot:actions>
                <x-admin.button type="submit" size="sm" icon="save">
                    <span x-text="submitting ? 'Saving…' : 'Save robots.txt'">Save robots.txt</span>
                </x-admin.button>
            </x-slot>
        @endcan

        <div class="space-y-4">
            <div
                class="flex items-start gap-2.5 rounded-lg border border-blue-100 bg-blue-50/60 px-3.5 py-3 text-xs text-blue-800 dark:border-blue-500/20 dark:bg-blue-500/10 dark:text-blue-200"
            >
                <x-admin.icon name="info" class="mt-px h-4 w-4 shrink-0" />
                <p>
                    Saved to
                    <code class="rounded bg-white/70 px-1 font-mono dark:bg-slate-900/60">public/robots.txt</code>
                    and served at
                    <a href="{{ url('robots.txt') }}" target="_blank" rel="noopener" class="font-medium underline underline-offset-2">/robots.txt</a>
                    . Keep the Sitemap line pointing to your live domain.
                </p>
            </div>

            <x-admin.form.textarea
                name="content"
                rows="14"
                :value="$robotsContent ?? ''"
                placeholder="User-agent: *&#10;Disallow:"
                aria-label="robots.txt content"
                class="font-mono text-xs leading-relaxed"
                spellcheck="false"
            />
        </div>
    </x-admin.card>
</form>
