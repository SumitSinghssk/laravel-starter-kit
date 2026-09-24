@php
    $siteHost = parse_url(url('/'), PHP_URL_HOST) ?: url('/');
    $schemaPlaceholder = implode("\n", [
        '{',
        '  "@context": "https://schema.org",',
        '  "@type": "LocalBusiness",',
        '  "name": "Your Company",',
        '  "url": "https://example.com"',
        '}',
    ]);
@endphp

<div
    x-data="{
        faqs: {{ isset($seo) && $seo->faqs ? json_encode($seo->faqs) : '[]' }},
        addFaq() {
            this.faqs.push({ question: '', answer: '' })
        },
        removeFaq(index) {
            this.faqs.splice(index, 1)
        },
        get faqsJson() {
            return JSON.stringify(this.faqs)
        },

        page: @js(old('page', $seo->page ?? ($defaultData['page'] ?? ''))),
        slug: @js(old('slug', $seo->slug ?? ($defaultData['slug'] ?? ''))),
        metaTitle: @js(old('meta_title', $seo->meta_title ?? '')),
        metaDescription: @js(old('meta_description', $seo->meta_description ?? '')),
        siteName: @js(\App\Helpers\Settings::appName()),
        siteHost: @js($siteHost),
        get previewTitle() {
            return this.metaTitle || this.page || 'Page title'
        },
        get previewPath() {
            return (this.slug || '')
                .split('/')
                .filter((part) => part.trim() !== '')
                .join(' › ')
        },
    }"
>
    <x-admin.form-grid>
        <x-admin.card title="Page" text="Which page these settings belong to." icon="file-text">
            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                <x-admin.form.input
                    name="page"
                    label="Page name"
                    required
                    x-model="page"
                    :value="$seo->page ?? $defaultData['page'] ?? ''"
                    placeholder="e.g. Home, About, Contact"
                >
                    <x-slot:leftIcon>
                        <x-admin.icon name="file" class="h-4 w-4" />
                    </x-slot>
                </x-admin.form.input>

                <x-admin.form.input
                    name="slug"
                    label="Slug"
                    required
                    x-model="slug"
                    :value="$seo->slug ?? $defaultData['slug'] ?? ''"
                    placeholder="e.g. / for home, about, my-blog-post"
                    hint="The URL path of the page."
                >
                    <x-slot:leftIcon>
                        <x-admin.icon name="link" class="h-4 w-4" />
                    </x-slot>
                </x-admin.form.input>
            </div>
        </x-admin.card>

        <x-admin.card title="Search appearance" text="The title and snippet search engines show for this page." icon="search">
            <div class="space-y-5">
                <div>
                    <x-admin.form.input
                        name="meta_title"
                        label="Meta title"
                        x-model="metaTitle"
                        :value="$seo->meta_title ?? ''"
                        placeholder="SEO page title (50–60 chars)"
                    >
                        <x-slot:leftIcon>
                            <x-admin.icon name="hash" class="h-4 w-4" />
                        </x-slot>
                    </x-admin.form.input>
                    <div class="mt-1.5 flex items-center justify-between gap-3 text-xs text-slate-500 dark:text-slate-400">
                        <span>Shown as the clickable headline in search results.</span>
                        <span
                            class="tabular shrink-0"
                            :class="metaTitle.length > 60 ? 'font-medium text-amber-600 dark:text-amber-400' : ''"
                            x-text="metaTitle.length + ' / 60'"
                        ></span>
                    </div>
                </div>

                <div>
                    <x-admin.form.textarea
                        name="meta_description"
                        label="Meta description"
                        rows="3"
                        x-model="metaDescription"
                        :value="$seo->meta_description ?? ''"
                        placeholder="Brief description of the page (150–160 chars recommended)..."
                    />
                    <div class="mt-1.5 flex justify-end text-xs text-slate-500 dark:text-slate-400">
                        <span
                            class="tabular"
                            :class="metaDescription.length > 160 ? 'font-medium text-amber-600 dark:text-amber-400' : ''"
                            x-text="metaDescription.length + ' / 160'"
                        ></span>
                    </div>
                </div>
            </div>
        </x-admin.card>

        <x-admin.card title="FAQs" text="Shown in this page's FAQ section and added to its FAQ structured data." icon="help-circle">
            <x-slot:extra>
                <span
                    class="tabular rounded-full border border-slate-200 bg-white px-2 py-px text-xs font-medium text-slate-600 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300"
                    x-text="faqs.length"
                ></span>
            </x-slot>

            <input type="hidden" name="faqs" :value="faqsJson" />

            <div class="space-y-3">
                <template x-for="(faq, index) in faqs" :key="index">
                    <div class="rounded-xl border border-slate-200 bg-slate-50/60 p-4 dark:border-slate-800 dark:bg-slate-950/40">
                        <div class="mb-3 flex items-center justify-between gap-3">
                            <span class="inline-flex items-center gap-2 text-xs font-medium text-slate-500 dark:text-slate-400">
                                <span
                                    class="tabular flex h-5 min-w-5 items-center justify-center rounded-md bg-white px-1 text-[11px] font-semibold text-slate-600 ring-1 ring-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:ring-slate-700"
                                    x-text="index + 1"
                                ></span>
                                Question
                            </span>
                            <button
                                type="button"
                                x-on:click="removeFaq(index)"
                                aria-label="Remove FAQ"
                                title="Remove FAQ"
                                class="flex h-7 w-7 cursor-pointer items-center justify-center rounded-lg text-slate-400 transition-colors hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-500/10 dark:hover:text-red-400"
                            >
                                <x-admin.icon name="trash" class="h-4 w-4" />
                            </button>
                        </div>

                        <div class="space-y-3">
                            <input
                                type="text"
                                x-model="faq.question"
                                placeholder="What is your question?"
                                aria-label="Question"
                                class="{{ \App\Support\FormField::controlClasses() }} px-3 py-2"
                            />
                            <textarea
                                x-model="faq.answer"
                                rows="3"
                                placeholder="Provide a clear, concise answer..."
                                aria-label="Answer"
                                class="{{ \App\Support\FormField::controlClasses() }} resize-y px-3 py-2"
                            ></textarea>
                        </div>
                    </div>
                </template>

                <template x-if="faqs.length === 0">
                    <div
                        class="flex flex-col items-center gap-1 rounded-xl border border-dashed border-slate-300 py-8 text-center dark:border-slate-700"
                    >
                        <span
                            class="mb-2 flex h-10 w-10 items-center justify-center rounded-xl bg-slate-100 text-slate-400 dark:bg-slate-800 dark:text-slate-500"
                        >
                            <x-admin.icon name="help-circle" class="h-5 w-5" />
                        </span>
                        <p class="text-sm font-medium text-slate-700 dark:text-slate-300">No FAQs added yet</p>
                        <p class="text-xs text-slate-500 dark:text-slate-400">Answer common questions about this page.</p>
                    </div>
                </template>

                <x-admin.button type="button" variant="secondary" icon="plus" x-on:click="addFaq()" class="w-full border-dashed">
                    Add FAQ
                </x-admin.button>
            </div>
        </x-admin.card>

        <x-admin.card title="Structured data" text="JSON-LD markup for rich results." icon="code">
            <x-admin.form.textarea
                name="schema"
                label="JSON-LD schema markup"
                description='Paste JSON-LD structured data (e.g. LocalBusiness, Product, Event). Plain JSON is enough; it is added to this page inside a <script type="application/ld+json"> tag automatically. FAQs are added separately.'
                rows="12"
                :value="$seo->schema ?? ''"
                :placeholder="$schemaPlaceholder"
                class="font-mono text-xs"
            />
        </x-admin.card>

        <x-admin.card title="Scripts & custom CSS" text="Code injected into this page only." icon="terminal">
            <div class="space-y-5">
                <fieldset class="space-y-5">
                    <x-admin.form.textarea
                        name="header_scripts"
                        label="Header scripts"
                        description="Scripts injected inside <head> (e.g. analytics, tag manager)."
                        rows="6"
                        :value="$seo->header_scripts ?? ''"
                        placeholder="<!-- e.g. Google Tag Manager -->&#10;<script>...</script>"
                        class="font-mono text-xs"
                    />

                    <x-admin.form.textarea
                        name="footer_scripts"
                        label="Footer scripts"
                        description="Scripts injected before </body>."
                        rows="6"
                        :value="$seo->footer_scripts ?? ''"
                        placeholder="<!-- e.g. chat widget, heatmap -->&#10;<script>...</script>"
                        class="font-mono text-xs"
                        wrapper-class="border-t border-slate-100 pt-5 dark:border-slate-800"
                    />

                    <x-admin.form.textarea
                        name="custom_css"
                        label="Custom CSS"
                        description="Page-specific CSS injected in the head. Wrap in <style> tags or write raw CSS."
                        rows="6"
                        :value="$seo->custom_css ?? ''"
                        placeholder=".hero { background: red; }"
                        class="font-mono text-xs"
                        wrapper-class="border-t border-slate-100 pt-5 dark:border-slate-800"
                    />
                </fieldset>
            </div>
        </x-admin.card>

        <x-slot:aside>
            <x-admin.card title="Search preview" text="Roughly how Google shows it." icon="globe">
                <div class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-950">
                    <div class="flex items-center gap-2.5">
                        <span
                            class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400"
                        >
                            <x-admin.icon name="globe" class="h-3.5 w-3.5" />
                        </span>
                        <div class="min-w-0 leading-tight">
                            <p class="truncate text-[13px] text-slate-800 dark:text-slate-200" x-text="siteName"></p>
                            <p class="truncate text-xs text-slate-500 dark:text-slate-400">
                                <span x-text="'https://' + siteHost"></span>
                                <span x-show="previewPath" x-text="' › ' + previewPath"></span>
                            </p>
                        </div>
                    </div>
                    <p class="mt-2.5 line-clamp-2 text-[17px] leading-snug text-[#1a0dab] dark:text-[#99c3ff]" x-text="previewTitle"></p>
                    <p
                        class="mt-1 line-clamp-3 text-[13px] leading-relaxed"
                        :class="metaDescription ? 'text-slate-600 dark:text-slate-400' : 'text-slate-400 italic dark:text-slate-500'"
                        x-text="metaDescription || 'Add a meta description to control the snippet shown here.'"
                    ></p>
                </div>
                <p class="mt-3 flex items-start gap-1.5 text-xs text-slate-500 dark:text-slate-400">
                    <x-admin.icon name="info" class="mt-px h-3.5 w-3.5 shrink-0" />
                    Search engines may rewrite titles and snippets.
                </p>
            </x-admin.card>

            <x-admin.card title="Indexing" icon="eye">
                <x-admin.form.toggle
                    name="index"
                    label="Allow search engine indexing"
                    hint="If disabled, this page will not appear in Google (noindex)."
                    label-position="left"
                    :checked="$seo->index ?? true"
                />
            </x-admin.card>

            <x-admin.card title="Social share image" icon="share">
                <div
                    x-data="{
                        preview:
                            '{{ isset($seo) && $seo->og_image ? asset('storage/' . $seo->og_image) : '' }}',
                        removeOgImage: false,
                        handleFileChange(event) {
                            const file = event.target.files[0]
                            if (file) {
                                this.preview = URL.createObjectURL(file)
                                this.removeOgImage = false
                            }
                        },
                        clearPreview() {
                            this.preview = ''
                            this.removeOgImage = true
                            this.$refs.ogFile.value = ''
                        },
                    }"
                >
                    <x-admin.image-upload
                        name="og_image"
                        preset="og"
                        remove-name="remove_og_image"
                        label="Open Graph image"
                        help="Shown when this page is shared on social media, WhatsApp or Slack."
                        :current="isset($seo) && $seo->og_image ? asset('storage/' . $seo->og_image) : null"
                    />
                </div>
            </x-admin.card>
        </x-slot>
    </x-admin.form-grid>
</div>
