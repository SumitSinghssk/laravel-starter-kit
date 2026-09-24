<div
    x-data="blogCategoryForm({
                preview:
                    '{{ isset($blogCategory) && $blogCategory->image ? asset('storage/' . $blogCategory->image) : '' }}',
                name: @js(old('name', $blogCategory->name ?? '')),
                slug: @js(old('slug', $blogCategory->slug ?? '')),
            })"
>
    <x-admin.form-grid>
        <x-admin.card title="Category details" text="The name, web address and a short description." icon="tag">
            <div class="space-y-5">
                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                    <x-admin.form.input
                        name="name"
                        label="Category name"
                        required
                        x-model="name"
                        :value="$blogCategory->name ?? ''"
                        placeholder="e.g. Technology, Lifestyle"
                    >
                        <x-slot:leftIcon>
                            <x-admin.icon name="tag" class="h-4 w-4" />
                        </x-slot>
                    </x-admin.form.input>

                    <x-admin.form.input
                        name="slug"
                        label="Slug"
                        required
                        x-model="slug"
                        x-on:input="onSlugInput"
                        :value="$blogCategory->slug ?? ''"
                        placeholder="e.g. technology, life-style"
                    >
                        <x-slot:leftIcon>
                            <x-admin.icon name="link" class="h-4 w-4" />
                        </x-slot>
                    </x-admin.form.input>
                </div>

                <x-admin.form.textarea
                    name="description"
                    label="Description"
                    rows="3"
                    :value="$blogCategory->description ?? ''"
                    placeholder="Brief description of this category..."
                />
            </div>
        </x-admin.card>

        <x-slot:aside>
            <x-admin.card title="Settings" icon="sliders">
                <div class="space-y-5">
                    <x-admin.form.select
                        name="status"
                        label="Status"
                        required
                        :options="\App\Enums\CommonStatusEnum::dotOptions()"
                        :value="isset($blogCategory) ? $blogCategory->status->value : \App\Enums\CommonStatusEnum::ACTIVE->value"
                    />

                    <x-admin.form.select
                        name="parent_id"
                        label="Parent category"
                        icon="folder-tree"
                        :options="['' => '— None (Top-level) —'] + $parentCategories->pluck('name', 'id')->all()"
                        :value="$blogCategory->parent_id ?? ''"
                    />
                </div>
            </x-admin.card>

            <x-admin.card title="Category image" icon="image">
                <x-admin.image-upload
                    name="image"
                    preset="category"
                    label="Category image"
                    :current="isset($blogCategory) && $blogCategory->image ? asset('storage/' . $blogCategory->image) : null"
                />
            </x-admin.card>
        </x-slot>
    </x-admin.form-grid>
</div>

<script>
    function blogCategoryForm(initial) {
        return {
            preview: initial.preview || '',
            removeImage: false,

            name: initial.name || '',
            slug: initial.slug || '',
            slugManuallyEdited: false,

            init() {
                this.$watch('name', (value) => {
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
