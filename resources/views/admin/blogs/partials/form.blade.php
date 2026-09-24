<div
    x-data="blogForm({
                preview:
                    '{{ isset($blog) && $blog->featured_image ? asset('storage/' . $blog->featured_image) : '' }}',
                title: @js(old('title', $blog->title ?? '')),
                slug: @js(old('slug', $blog->slug ?? '')),
            })"
>
    <x-admin.form-grid>
        <x-admin.card title="Post details" text="The headline, its web address and a short summary." icon="file-text">
            <div class="space-y-5">
                <x-admin.form.input
                    name="title"
                    label="Post title"
                    required
                    x-model="title"
                    :value="$blog->title ?? ''"
                    placeholder="e.g. Getting Started with Laravel"
                />

                <x-admin.form.input
                    name="slug"
                    label="Slug"
                    required
                    x-model="slug"
                    x-on:input="onSlugInput"
                    :value="$blog->slug ?? ''"
                    placeholder="e.g. getting-started-with-laravel"
                    hint="Filled in from the title. Letters, numbers and dashes only."
                >
                    <x-slot:leftIcon>
                        <x-admin.icon name="link" class="h-4 w-4" />
                    </x-slot>
                </x-admin.form.input>

                <x-admin.form.input
                    name="excerpt"
                    label="Excerpt"
                    :value="$blog->excerpt ?? ''"
                    placeholder="Short summary shown in listings…"
                    hint="One or two sentences shown on blog cards and in search results."
                />
            </div>
        </x-admin.card>

        <x-admin.card title="Content" text="The body of the post." icon="newspaper">
            <x-admin.form.textarea
                name="content"
                label="Content"
                required
                rows="12"
                editor
                :value="$blog->content ?? ''"
                placeholder="Write your blog post content here..."
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
                        :value="isset($blog) ? $blog->status->value : \App\Enums\CommonStatusEnum::ACTIVE->value"
                    />

                    <x-admin.form.date-picker
                        name="published_at"
                        label="Publish date"
                        with-time
                        :value="isset($blog) && $blog->published_at ? $blog->published_at->format('Y-m-d\TH:i') : ''"
                        hint="Leave empty to publish now. A future date schedules the post."
                    />
                </div>
            </x-admin.card>

            <x-admin.card title="Organisation" icon="tag">
                <x-admin.form.multi-select
                    name="category_ids"
                    label="Categories"
                    placeholder="Select categories…"
                    :options="\App\Support\FormField::tree($categories)"
                    :value="$selectedCategoryIds ?? []"
                />
            </x-admin.card>

            <x-admin.card title="Featured image" icon="image">
                <x-admin.image-upload
                    name="featured_image"
                    preset="blog"
                    label="Featured image"
                    :current="isset($blog) && $blog->featured_image ? asset('storage/' . $blog->featured_image) : null"
                />
            </x-admin.card>
        </x-slot>
    </x-admin.form-grid>
</div>

@push('scripts')
    <script defer>
        function blogForm(initial) {
            return {
                preview: initial.preview || '',
                removeImage: false,

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

                handleFileChange(event) {
                    const file = event.target.files[0];
                    if (file) {
                        this.preview = URL.createObjectURL(file);
                        this.removeImage = false;
                    }
                },

                clearPreview() {
                    this.preview = '';
                    this.removeImage = true;
                    this.$refs.imageFile.value = '';
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
