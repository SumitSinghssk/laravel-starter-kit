@php
    $canUpdate = auth()
        ->user()
        ->can("admin.settings.scripts.update");
@endphp

<form
    @if ($canUpdate)
        action="{{ route("admin.settings.scripts.update") }}"
        method="POST"
    @endif
    x-data="{ submitting: false }"
    x-on:submit="submitting = true"
    class="space-y-6"
>
    @csrf
    @method("POST")

    @unless ($canUpdate)
        @include("admin.settings.partials.read-only-notice")
    @endunless

    <x-admin.card title="Scripts" text="Tracking tags, pixels and widgets added to every public page." icon="code">
        <div class="space-y-6">
            <x-admin.form.textarea
                name="header_scripts"
                label="Header scripts"
                description="Injected inside the <head> tag."
                rows="8"
                :disabled="! $canUpdate"
                :value="$settings['header_scripts'] ?? ''"
                placeholder="<!-- Paste your <script> or meta tags here -->"
                hint="Example: Google Tag Manager, Facebook Pixel initialization scripts."
                class="font-mono text-xs"
                spellcheck="false"
            />

            <div class="border-t border-slate-100 pt-6 dark:border-slate-800">
                <x-admin.form.textarea
                    name="footer_scripts"
                    label="Footer scripts"
                    description="Injected just before the closing </body> tag."
                    rows="8"
                    :disabled="! $canUpdate"
                    :value="$settings['footer_scripts'] ?? ''"
                    placeholder="<!-- Paste your deferred <script> tags here -->"
                    hint="Example: Chat widgets, lazy-load libraries."
                    class="font-mono text-xs"
                    spellcheck="false"
                />
            </div>
        </div>
    </x-admin.card>

    <x-admin.card title="Custom CSS" text="Style overrides applied to the public website." icon="palette">
        <div class="space-y-6">
            <x-admin.form.textarea
                name="header_css"
                label="Header CSS"
                description="Injected inside the <head> tag."
                rows="8"
                :disabled="! $canUpdate"
                :value="$settings['header_css'] ?? ''"
                placeholder="/* Your custom CSS here */"
                class="font-mono text-xs"
                spellcheck="false"
            />

            <div class="border-t border-slate-100 pt-6 dark:border-slate-800">
                <x-admin.form.textarea
                    name="footer_css"
                    label="Footer CSS"
                    description="Additional CSS injected just before </body>."
                    rows="8"
                    :disabled="! $canUpdate"
                    :value="$settings['footer_css'] ?? ''"
                    placeholder="/* Your override CSS here */"
                    class="font-mono text-xs"
                    spellcheck="false"
                />
            </div>
        </div>
    </x-admin.card>

    @if ($canUpdate)
        @include("admin.settings.partials.save-bar", ["label" => "Save scripts", "note" => "Scripts and CSS run on every public page — test changes carefully."])
    @endif
</form>
