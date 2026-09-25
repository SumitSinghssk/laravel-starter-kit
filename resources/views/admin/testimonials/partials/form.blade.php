<div
    x-data="{
        name: @js(old('name', $testimonial->name ?? '')),
        designation: @js(old('designation', $testimonial->designation ?? '')),
        company: @js(old('company', $testimonial->company ?? '')),
        quote: @js(old('quote', $testimonial->quote ?? '')),
        rating: @js((string) old('rating', $testimonial ? $testimonial->rating : 5)),
        get byLine() {
            return [this.designation, this.company]
                .filter((part) => part.trim() !== '')
                .join(', ')
        },
        get initials() {
            return (
                this.name
                    .trim()
                    .split(/\s+/)
                    .filter(Boolean)
                    .slice(0, 2)
                    .map((part) => part[0].toUpperCase())
                    .join('') || '?'
            )
        },
    }"
>
    <x-admin.form-grid>
        <x-admin.card title="Client" text="Who gave the testimonial." icon="user">
            <div class="space-y-5">
                <x-admin.form.input
                    name="name"
                    label="Client name"
                    required
                    x-model="name"
                    :value="$testimonial->name ?? ''"
                    placeholder="e.g. Priya Nair"
                >
                    <x-slot:leftIcon>
                        <x-admin.icon name="user" class="h-4 w-4" />
                    </x-slot>
                </x-admin.form.input>

                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                    <x-admin.form.input
                        name="designation"
                        label="Designation"
                        x-model="designation"
                        :value="$testimonial->designation ?? ''"
                        placeholder="e.g. Head of Product"
                    >
                        <x-slot:leftIcon>
                            <x-admin.icon name="briefcase" class="h-4 w-4" />
                        </x-slot>
                    </x-admin.form.input>

                    <x-admin.form.input
                        name="company"
                        label="Company"
                        x-model="company"
                        :value="$testimonial->company ?? ''"
                        placeholder="e.g. Finlytic"
                    >
                        <x-slot:leftIcon>
                            <x-admin.icon name="building" class="h-4 w-4" />
                        </x-slot>
                    </x-admin.form.input>
                </div>
            </div>
        </x-admin.card>

        <x-admin.card title="Testimonial" text="Their words, as they should appear on the website." icon="message">
            <div class="space-y-5">
                <div>
                    <x-admin.form.textarea
                        name="quote"
                        label="Testimonial"
                        required
                        rows="5"
                        x-model="quote"
                        :value="$testimonial->quote ?? ''"
                        placeholder="What did they say about working with you?"
                    />
                    <div class="mt-1.5 flex items-center justify-between gap-3 text-xs text-slate-500 dark:text-slate-400">
                        <span>Plain text, without quotation marks. 2–4 sentences read best.</span>
                        <span
                            class="tabular shrink-0"
                            :class="quote.length > 2000 ? 'font-medium text-red-600 dark:text-red-400' : ''"
                            x-text="quote.length + ' / 2000'"
                        ></span>
                    </div>
                </div>

                <x-admin.form.select
                    name="rating"
                    label="Rating"
                    x-model="rating"
                    :options="['' => 'No rating'] + collect(\App\Models\Testimonial::RATINGS)->map(fn ($label, $stars) => str_repeat('★', $stars) . str_repeat('☆', 5 - $stars) . '  ' . $label)->all()"
                    :value="$testimonial ? (string) $testimonial->rating : '5'"
                    hint="Optional star rating shown with the testimonial."
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
                        :value="isset($testimonial) ? $testimonial->status->value : \App\Enums\CommonStatusEnum::ACTIVE->value"
                        hint="Only active testimonials are shown on the website."
                    />

                    <x-admin.form.toggle
                        name="is_featured"
                        label="Featured"
                        hint="Featured testimonials are shown first."
                        :checked="$testimonial->is_featured ?? false"
                    />

                    <x-admin.form.input
                        type="number"
                        name="sort_order"
                        label="Sort order"
                        min="0"
                        :value="$testimonial->sort_order ?? 0"
                        hint="Lower numbers come first."
                    />
                </div>
            </x-admin.card>

            <x-admin.card title="Photo" icon="image">
                <x-admin.image-upload
                    name="photo"
                    preset="avatar"
                    label="Client photo"
                    help="Optional. A square headshot or company logo."
                    :current="$testimonial?->photo_url ?? null"
                />
            </x-admin.card>
        </x-slot>
    </x-admin.form-grid>
</div>
