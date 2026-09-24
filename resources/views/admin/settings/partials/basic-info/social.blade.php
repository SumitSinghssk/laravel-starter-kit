<x-admin.card id="settings-social" class="scroll-mt-24" title="Social" text="Profiles linked from the website footer." icon="share">
    @if ($canUpdate)
        <x-slot:actions>
            <x-admin.button type="button" variant="secondary" size="sm" icon="plus" x-on:click="addSocial()">Add platform</x-admin.button>
        </x-slot>
    @endif

    <div class="space-y-3">
        <template x-for="(social, index) in social_links" :key="index">
            <div
                class="flex items-start gap-2 rounded-xl border border-slate-200 bg-slate-50/50 p-3 sm:p-4 dark:border-slate-800 dark:bg-slate-800/30"
            >
                <div class="grid min-w-0 flex-1 grid-cols-1 gap-3 sm:grid-cols-[minmax(0,14rem)_minmax(0,1fr)]">
                    <div>
                        <input type="hidden" x-bind:name="'social_links[' + index + '][platform]'" x-bind:value="social.platform" />
                        <x-admin.form.select
                            :id="false"
                            label="Platform"
                            x-model="social.platform"
                            placeholder="Select platform…"
                            :disabled="! $canUpdate"
                            :options="array_combine($platforms = ['Facebook', 'Instagram', 'X (Twitter)', 'LinkedIn', 'YouTube', 'TikTok', 'Pinterest', 'WhatsApp', 'GitHub'], $platforms)"
                        />
                    </div>

                    <x-admin.form.input
                        type="url"
                        :id="false"
                        label="Profile URL"
                        x-bind:name="'social_links[' + index + '][url]'"
                        x-model="social.url"
                        x-bind:aria-label="(social.platform || 'Profile') + ' URL'"
                        :disabled="! $canUpdate"
                        placeholder="https://…"
                    >
                        <x-slot:leftIcon>
                            <x-admin.icon name="link" class="h-4 w-4" />
                        </x-slot>
                    </x-admin.form.input>
                </div>

                @if ($canUpdate)
                    <button
                        type="button"
                        x-on:click="removeSocial(index)"
                        aria-label="Remove social link"
                        title="Remove"
                        class="mt-7 flex h-9 w-9 shrink-0 cursor-pointer items-center justify-center rounded-lg text-slate-400 transition hover:bg-red-50 hover:text-red-600 focus:outline-none focus-visible:ring-3 focus-visible:ring-red-500/30 dark:hover:bg-red-500/10 dark:hover:text-red-400"
                    >
                        <x-admin.icon name="trash" class="h-4 w-4" />
                    </button>
                @endif
            </div>
        </template>

        <template x-if="social_links.length === 0">
            <div
                class="flex flex-col items-center gap-3 rounded-xl border-2 border-dashed border-slate-200 px-4 py-10 text-center dark:border-slate-800"
            >
                <span
                    class="flex h-11 w-11 items-center justify-center rounded-xl border border-slate-200 bg-slate-50 text-slate-400 dark:border-slate-700 dark:bg-slate-800"
                >
                    <x-admin.icon name="share" class="h-5 w-5" />
                </span>
                <div>
                    <p class="text-sm font-medium text-slate-700 dark:text-slate-300">No social platforms linked</p>
                    <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">Add your profiles so visitors can follow you.</p>
                </div>
                @if ($canUpdate)
                    <x-admin.button type="button" variant="secondary" size="sm" icon="plus" x-on:click="addSocial()">
                        Add your first platform
                    </x-admin.button>
                @endif
            </div>
        </template>
    </div>
</x-admin.card>
