@php
    $siteUrl = rtrim(url('/'), '/');
@endphp

<div
    x-data="{
        source: @js(old('source_path', $redirect->source_path ?? '')),
        target: @js(old('target_url', $redirect->target_url ?? '')),
        code: @js((string) old('status_code', $redirect->status_code ?? 301)),
        siteUrl: @js($siteUrl),
        get sourcePreview() {
            const path = this.source
                .trim()
                .replace(/^https?:\/\/[^/]+/i, '')
                .split(/[?#]/)[0]
                .replace(/^\/+|\/+$/g, '')
            return this.siteUrl + '/' + path.toLowerCase()
        },
        get targetPreview() {
            const target = this.target.trim()
            if (! target) return ''
            return /^https?:\/\//i.test(target)
                ? target
                : this.siteUrl + '/' + target.replace(/^\/+/, '')
        },
    }"
>
    <x-admin.form-grid>
        <x-admin.card title="Redirect" text="Where visitors come from and where they should land." icon="redirect">
            <div class="space-y-5">
                <x-admin.form.input
                    name="source_path"
                    label="Old URL"
                    required
                    x-model="source"
                    :value="$redirect->source_path ?? ''"
                    placeholder="/old-page"
                    hint="A path on this site. Letter case, a trailing slash and anything after ? are ignored."
                >
                    <x-slot:leftIcon>
                        <x-admin.icon name="link" class="h-4 w-4" />
                    </x-slot>
                </x-admin.form.input>

                <x-admin.form.input
                    name="target_url"
                    label="New URL"
                    required
                    x-model="target"
                    :value="$redirect->target_url ?? ''"
                    placeholder="/new-page or https://example.com/page"
                    hint="A path on this site or a full URL to another website."
                >
                    <x-slot:leftIcon>
                        <x-admin.icon name="arrow-right" class="h-4 w-4" />
                    </x-slot>
                </x-admin.form.input>

                <x-admin.form.textarea
                    name="note"
                    label="Note"
                    rows="2"
                    :value="$redirect->note ?? ''"
                    placeholder="Optional: why this redirect exists, e.g. “Old blog URL after the 2026 redesign”."
                />
            </div>
        </x-admin.card>

        <x-slot:aside>
            <x-admin.card title="Settings" icon="settings">
                <div class="space-y-5">
                    <x-admin.form.select
                        name="status_code"
                        label="Redirect type"
                        required
                        x-model="code"
                        :options="\App\Models\Redirect::STATUS_CODES"
                        :value="(string) ($redirect->status_code ?? 301)"
                        hint="Use 301 unless the move is temporary."
                    />

                    <x-admin.form.select
                        name="status"
                        label="Status"
                        required
                        :options="\App\Enums\CommonStatusEnum::dotOptions()"
                        :value="isset($redirect) ? $redirect->status->value : \App\Enums\CommonStatusEnum::ACTIVE->value"
                        hint="Inactive redirects are kept but not applied."
                    />
                </div>
            </x-admin.card>

            <x-admin.card title="Preview" text="What a visitor experiences." icon="eye">
                <div class="space-y-2 text-xs">
                    <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 dark:border-slate-800 dark:bg-slate-950/40">
                        <span class="block text-[11px] font-medium tracking-wide text-slate-400 uppercase">Visitor opens</span>
                        <span class="block font-mono break-all text-slate-700 dark:text-slate-200" x-text="sourcePreview"></span>
                    </div>

                    <div class="flex items-center gap-2 pl-3 text-slate-500 dark:text-slate-400">
                        <x-admin.icon name="arrow-right" class="h-3.5 w-3.5 rotate-90" />
                        <span class="tabular font-semibold" x-text="code"></span>
                        <span x-text="['301', '308'].includes(code) ? 'permanent redirect' : 'temporary redirect'"></span>
                    </div>

                    <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 dark:border-slate-800 dark:bg-slate-950/40">
                        <span class="block text-[11px] font-medium tracking-wide text-slate-400 uppercase">Lands on</span>
                        <span class="block font-mono break-all text-slate-700 dark:text-slate-200" x-text="targetPreview || '—'"></span>
                    </div>
                </div>
            </x-admin.card>
        </x-slot>
    </x-admin.form-grid>
</div>
