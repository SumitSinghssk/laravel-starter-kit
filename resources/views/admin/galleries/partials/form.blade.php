<div
    x-data="{
        title: @js(old('title', $gallery->title ?? '')),
        slug: @js(old('slug', $gallery->slug ?? '')),
        slugEdited: @js(filled(old('slug', $gallery->slug ?? ''))),
        slugify(value) {
            return value
                .toLowerCase()
                .normalize('NFKD')
                .replace(/[̀-ͯ]/g, '')
                .replace(/[^a-z0-9\s-]/g, '')
                .trim()
                .replace(/[\s-]+/g, '-')
        },
        init() {
            this.$watch('title', (value) => {
                if (! this.slugEdited) this.slug = this.slugify(value)
            })
        },
    }"
>
    <x-admin.form-grid>
        <x-admin.card title="Album details" text="What the album is about." icon="image">
            <div class="space-y-5">
                <x-admin.form.input
                    name="title"
                    label="Title"
                    required
                    x-model="title"
                    :value="$gallery->title ?? ''"
                    placeholder="e.g. Annual Day 2026"
                >
                    <x-slot:leftIcon>
                        <x-admin.icon name="image" class="h-4 w-4" />
                    </x-slot>
                </x-admin.form.input>

                <x-admin.form.input
                    name="slug"
                    label="Slug"
                    x-model="slug"
                    x-on:input="slugEdited = slug.trim() !== ''"
                    :value="$gallery->slug ?? ''"
                    placeholder="annual-day-2026"
                    hint="Used in the album's web address. Filled in from the title; leave empty to reset."
                >
                    <x-slot:leftIcon>
                        <x-admin.icon name="link" class="h-4 w-4" />
                    </x-slot>
                </x-admin.form.input>

                <x-admin.form.textarea
                    name="description"
                    label="Description"
                    rows="4"
                    :value="$gallery->description ?? ''"
                    placeholder="Optional: a short introduction shown above the photos."
                />

                <x-admin.form.date-picker
                    name="event_date"
                    label="Event date"
                    :value="isset($gallery) && $gallery->event_date ? $gallery->event_date->format('Y-m-d') : ''"
                    hint="Optional: when the photos or videos were taken."
                />
            </div>
        </x-admin.card>

        <x-slot:aside>
            <x-admin.card title="Publishing" icon="calendar">
                <div class="space-y-5">
                    <x-admin.form.select
                        name="status"
                        label="Status"
                        required
                        :options="\App\Enums\CommonStatusEnum::dotOptions()"
                        :value="isset($gallery) ? $gallery->status->value : \App\Enums\CommonStatusEnum::ACTIVE->value"
                        hint="Only active albums are shown on the website."
                    />

                    <x-admin.form.toggle
                        name="is_featured"
                        label="Featured"
                        hint="Featured albums are shown first."
                        :checked="$gallery->is_featured ?? false"
                    />

                    <x-admin.form.input
                        type="number"
                        name="sort_order"
                        label="Sort order"
                        min="0"
                        :value="$gallery->sort_order ?? 0"
                        hint="Lower numbers come first."
                    />
                </div>
            </x-admin.card>
        </x-slot>
    </x-admin.form-grid>
</div>
