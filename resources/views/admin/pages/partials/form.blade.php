<div
    x-data="pageForm({
                title: @js(old('title', $page->title ?? '')),
                slug: @js(old('slug', $page->slug ?? '')),
            })"
>
    <x-admin.form-grid>
        <x-admin.card title="Page details" text="The page name and its web address." icon="file-text">
            <div class="space-y-5">
                <x-admin.form.input name="title" label="Page title" required x-model="title" :value="$page->title ?? ''" placeholder="e.g. About Us">
                    <x-slot:leftIcon>
                        <x-admin.icon name="file" class="h-4 w-4" />
                    </x-slot>
                </x-admin.form.input>

                <div>
                    <x-admin.form.input
                        name="slug"
                        label="Slug"
                        required
                        x-model="slug"
                        x-on:input="onSlugInput"
                        :value="$page->slug ?? ''"
                        placeholder="e.g. privacy-policy"
                    >
                        <x-slot:leftIcon>
                            <x-admin.icon name="link" class="h-4 w-4" />
                        </x-slot>
                    </x-admin.form.input>
                    <p class="mt-1.5 flex min-w-0 items-center gap-1.5 text-xs text-slate-500 dark:text-slate-400">
                        <x-admin.icon name="globe" class="h-3.5 w-3.5 shrink-0" />
                        <span class="shrink-0">Public URL:</span>
                        <span class="truncate font-mono text-slate-700 dark:text-slate-300">
                            {{ url('/') }}/
                            <span x-text="slug || 'your-slug'"></span>
                        </span>
                    </p>
                </div>
            </div>
        </x-admin.card>

        <x-admin.card title="Content" text="The body of the page." icon="newspaper">
            <x-admin.form.textarea
                name="content"
                label="Content"
                required
                rows="12"
                editor
                :value="$page->content ?? ''"
                placeholder="Write your page content here..."
            />
        </x-admin.card>

        <x-slot:aside>
            <x-admin.card title="Publishing" icon="calendar">
                <div class="space-y-5">
                    <x-admin.form.select
                        name="status"
                        label="Status"
                        required
                        :options="\App\Enums\CommonStatusEnum::dotOptions()"
                        :value="isset($page) ? $page->status->value : \App\Enums\CommonStatusEnum::ACTIVE->value"
                    />

                    <x-admin.form.date-picker
                        name="published_at"
                        label="Publish date"
                        with-time
                        :value="isset($page) && $page->published_at ? $page->published_at->format('Y-m-d\TH:i') : ''"
                        hint="Leave empty to publish now. A future date schedules the page."
                    />
                </div>
            </x-admin.card>

            <x-admin.card title="Banner image" icon="image">
                <x-admin.image-upload
                    name="featured_image"
                    preset="page"
                    label="Page banner"
                    :current="isset($page) && $page->featured_image ? asset('storage/' . $page->featured_image) : null"
                />
            </x-admin.card>
        </x-slot>
    </x-admin.form-grid>
</div>

@push('scripts')
    <script defer>
        function pageForm(initial) {
            return {
                title: initial.title || '',
                slug: initial.slug || '',
                slugManuallyEdited: false,

                init() {
                    this.$watch('title', (value) => {
                        if (!this.slugManuallyEdited) {
                            this.slug = this.generateSlug(value);
                        }
                    });
                },

                onSlugInput() {
                    this.slugManuallyEdited = true;
                    if (this.slug.trim() === '') {
                        this.slugManuallyEdited = false;
                    }
                },

                generateSlug(value) {
                    return value
                        .toLowerCase()
                        .trim()
                        .replace(/[^a-z0-9\s-]/g, '')
                        .replace(/\s+/g, '-')
                        .replace(/-+/g, '-');
                },
            };
        }
    </script>
@endpush
