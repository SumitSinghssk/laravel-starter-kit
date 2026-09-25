@php
    use App\Support\EmailTemplates;
    use App\Support\LocalTime;
    use Illuminate\Support\Carbon;
    use Illuminate\Support\Js;

    $canEdit = auth()
        ->user()
        ->can('admin.settings.email-templates.update');
    $design = EmailTemplates::design();
    $fieldLabels = ['subject' => 'Subject', 'heading' => 'Heading', 'body' => 'Message', 'button' => 'Button label', 'note' => 'Small print'];
    $fieldHints = [
        'subject' => 'What people see in their inbox.',
        'heading' => 'Big title at the top of the email. Leave empty to hide it.',
        'body' => 'Leave an empty line between paragraphs. Put **stars** around words to make them bold. Web addresses become links.',
        'button' => 'The button opens the right page by itself.',
        'note' => 'Smaller text under the details. Leave empty to hide it.',
    ];

    $templates = collect(EmailTemplates::definitions())
        ->map(function (array $definition, string $key) use ($fieldHints) {
            $custom = EmailTemplates::custom($key);

            return [
                'key' => $key,
                'group' => $definition['group'],
                'label' => $definition['label'],
                'description' => $definition['description'],
                'optional' => $definition['optional'],
                'hints' =>
                    $key === 'enquiry_reply'
                        ? [
                            'body' => 'Plain text, sent exactly as typed. The empty lines in the middle are where you write each reply.',
                            'note' => 'The line at the very bottom of the reply. Leave empty to use the footer line from Email look.',
                        ]
                        : $fieldHints,
                'fields' => $definition['fields'],
                'placeholders' => collect($definition['placeholders'])
                    ->map(fn ($meta, $name) => ['name' => $name, 'label' => $meta[0]])
                    ->values(),
                'values' => EmailTemplates::values($key),
                'defaults' => EmailTemplates::defaults($key),
                'enabled' => EmailTemplates::enabled($key),
                'customised' => EmailTemplates::isCustomised($key),
                'updated' => $custom && filled($custom['updated_at'] ?? null) ? 'Edited ' . LocalTime::dateTime(Carbon::parse($custom['updated_at'])) . (filled($custom['updated_by'] ?? null) ? ' by ' . $custom['updated_by'] : '') : null,
            ];
        })
        ->all();

    $oldTemplate = old('_template');
    $old =
        $oldTemplate && isset($templates[$oldTemplate])
            ? [
                'values' => collect($templates[$oldTemplate]['fields'])
                    ->mapWithKeys(fn ($field) => [$field => (string) old($field, '')])
                    ->all(),
                'enabled' => (bool) old('enabled', true),
            ]
            : null;
    $templateErrors = $errors->getBag('template');
@endphp

<div
    class="scroll-mt-6 space-y-5"
    x-data="emailTemplates(
                {{
                    Js::from([
                        'templates' => $templates,
                        'groups' => EmailTemplates::GROUPS,
                        'initial' => $oldTemplate ?? request('template'),
                        'old' => $old,
                        'previewUrl' => route('admin.settings.email-templates.preview', '__KEY__'),
                        'testUrl' => route('admin.settings.email-templates.test', '__KEY__'),
                        'testEmail' => auth()->user()->email,
                    ])
                }},
            )"
>
    @unless ($canEdit)
        @include('admin.settings.partials.read-only-notice')
    @endunless

    <template x-if="! current">
        <div class="space-y-5">
            <x-admin.card
                title="Email templates"
                text="The words in every email the website sends. Placeholders like {name} are filled in for each person."
                icon="mail"
                :padded="false"
            >
                <template x-for="group in groups" :key="group.id">
                    <div class="border-b border-slate-100 last:border-b-0 dark:border-slate-800">
                        <p
                            class="bg-slate-50/70 px-4 py-2 text-[11px] font-semibold tracking-wide text-slate-500 uppercase sm:px-5 dark:bg-slate-800/40 dark:text-slate-400"
                            x-text="group.label"
                        ></p>
                        <ul class="divide-y divide-slate-100 dark:divide-slate-800">
                            <template x-for="item in group.items" :key="item.key">
                                <li>
                                    <button
                                        type="button"
                                        x-on:click="open(item.key)"
                                        class="group flex w-full cursor-pointer items-center gap-4 px-4 py-3.5 text-left transition hover:bg-slate-50 focus:outline-none focus-visible:bg-slate-50 sm:px-5 dark:hover:bg-slate-800/40 dark:focus-visible:bg-slate-800/40"
                                    >
                                        <span
                                            class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-400 transition group-hover:text-blue-600 dark:border-slate-700 dark:bg-slate-900"
                                        >
                                            <x-admin.icon name="mail" class="h-4 w-4" />
                                        </span>
                                        <span class="min-w-0 flex-1">
                                            <span class="flex flex-wrap items-center gap-2">
                                                <span class="text-sm font-medium text-slate-900 dark:text-white" x-text="item.label"></span>
                                                <span
                                                    x-show="! item.enabled"
                                                    class="rounded-full bg-amber-50 px-2 py-px text-[11px] font-semibold text-amber-700 ring-1 ring-amber-100 dark:bg-amber-500/10 dark:text-amber-300 dark:ring-amber-500/20"
                                                >
                                                    Not sent
                                                </span>
                                                <span
                                                    x-show="item.customised && item.enabled"
                                                    class="rounded-full bg-blue-50 px-2 py-px text-[11px] font-semibold text-blue-700 ring-1 ring-blue-100 dark:bg-blue-500/10 dark:text-blue-300 dark:ring-blue-500/20"
                                                >
                                                    Edited
                                                </span>
                                            </span>
                                            <span
                                                class="mt-0.5 block truncate text-xs text-slate-500 dark:text-slate-400"
                                                x-text="item.description"
                                            ></span>
                                        </span>
                                        <x-admin.icon
                                            name="chevron-right"
                                            class="h-4 w-4 shrink-0 text-slate-300 transition group-hover:translate-x-0.5 group-hover:text-slate-500"
                                        />
                                    </button>
                                </li>
                            </template>
                        </ul>
                    </div>
                </template>
            </x-admin.card>

            <form
                method="POST"
                action="{{ route('admin.settings.email-templates.design') }}"
                x-data="{
                    saving: false,
                    accent: {{ Js::from(old('accent', $design['accent'])) }},
                }"
                x-on:submit="saving = true; submitting = true"
            >
                @csrf
                @method('PUT')

                <x-admin.card title="Email look" text="Shared by every email: logo, colour and the line at the bottom." icon="palette">
                    @if ($canEdit)
                        <x-slot:actions>
                            <x-admin.button size="sm" icon="save" x-bind:disabled="saving">
                                <span x-text="saving ? 'Saving…' : 'Save look'">Save look</span>
                            </x-admin.button>
                        </x-slot>
                    @endif

                    <fieldset @disabled(! $canEdit) class="space-y-5">
                        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                            <x-admin.form.field
                                label="Button & accent colour"
                                for="accent"
                                :error="$errors->design->first('accent')"
                                hint="Used for buttons, links and the line at the top."
                            >
                                <div class="flex items-center gap-2">
                                    <input
                                        type="color"
                                        x-model="accent"
                                        aria-label="Pick a colour"
                                        class="h-9 w-12 shrink-0 cursor-pointer rounded-lg border border-slate-200 bg-white p-1 dark:border-slate-700 dark:bg-slate-900"
                                    />
                                    <x-admin.form.input
                                        name="accent"
                                        id="accent"
                                        x-model="accent"
                                        maxlength="7"
                                        class="font-mono"
                                        wrapper-class="flex-1"
                                    />
                                </div>
                            </x-admin.form.field>

                            <x-admin.form.input
                                name="footer"
                                label="Footer line"
                                :value="$design['footer']"
                                maxlength="200"
                                :error="$errors->design->first('footer')"
                                hint="Shown after the website name at the very bottom."
                            />
                        </div>

                        <x-admin.form.toggle
                            name="show_logo"
                            label="Show the logo at the top"
                            :checked="$design['show_logo']"
                            :disabled="! $canEdit"
                            :hint="\App\Helpers\Settings::logoLight() ? 'Uses the light logo from Business profile → Branding.' : 'No logo uploaded yet. Add one in Business profile → Branding.'"
                        />
                    </fieldset>
                </x-admin.card>
            </form>
        </div>
    </template>

    <template x-if="current">
        <form
            method="POST"
            x-bind:action="url({{ Js::from(route('admin.settings.email-templates.update', '__KEY__')) }})"
            x-on:submit="submitting = true"
            class="space-y-5"
        >
            @csrf
            @method('PUT')
            <input type="hidden" name="_template" x-bind:value="current" />

            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex min-w-0 items-center gap-3">
                    <x-admin.button type="button" variant="secondary" size="sm" icon="arrow-left" x-on:click="close()">All emails</x-admin.button>
                    <div class="min-w-0">
                        <h2 class="truncate text-base font-semibold text-slate-900 dark:text-white" x-text="template.label"></h2>
                        <p class="truncate text-xs text-slate-500 dark:text-slate-400" x-text="template.updated ?? 'Original text'"></p>
                    </div>
                </div>

                @if ($canEdit)
                    <div class="flex flex-wrap items-center gap-2">
                        <x-admin.button
                            type="button"
                            variant="ghost"
                            size="sm"
                            icon="history"
                            x-show="template.customised"
                            x-on:click="$dispatch('open-modal', 'restore-email-template')"
                        >
                            Restore original
                        </x-admin.button>
                        <x-admin.button type="button" variant="secondary" size="sm" icon="send" x-on:click="testOpen = ! testOpen">
                            Send test
                        </x-admin.button>
                        <x-admin.button size="sm" icon="save" x-bind:disabled="submitting">
                            <span x-text="submitting ? 'Saving…' : 'Save email'">Save email</span>
                        </x-admin.button>
                    </div>
                @endif
            </div>

            @if ($canEdit)
                <div
                    x-show="testOpen"
                    x-transition
                    class="rounded-xl border border-slate-200/80 bg-white p-4 shadow-xs dark:border-slate-800 dark:bg-slate-900"
                >
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
                        <x-admin.form.input
                            id="template-test-email"
                            type="email"
                            label="Send a test to"
                            x-model="testEmail"
                            x-on:keydown.enter.prevent="sendTest()"
                            wrapper-class="flex-1"
                            hint="Uses what you typed (even unsaved) with example details."
                        />
                        <x-admin.button type="button" icon="send" x-on:click="sendTest()" x-bind:disabled="testing || ! testEmail" class="sm:mb-6">
                            <span x-text="testing ? 'Sending…' : 'Send test'">Send test</span>
                        </x-admin.button>
                    </div>
                    <p
                        x-show="testResult"
                        x-cloak
                        role="status"
                        class="mt-1 flex items-start gap-2 text-sm"
                        x-bind:class="
                            testResult?.ok
                                ? 'text-emerald-700 dark:text-emerald-300'
                                : 'text-amber-700 dark:text-amber-300'
                        "
                    >
                        <x-admin.icon name="check-circle" class="mt-0.5 h-4 w-4 shrink-0" x-show="testResult?.ok" />
                        <x-admin.icon name="alert-triangle" class="mt-0.5 h-4 w-4 shrink-0" x-show="! testResult?.ok" />
                        <span x-text="testResult?.message"></span>
                    </p>
                </div>
            @endif

            <div class="grid grid-cols-1 gap-5 2xl:grid-cols-2 2xl:items-start">
                <x-admin.card icon="pencil">
                    <x-slot:title>
                        <h2 class="text-sm font-semibold text-slate-900 dark:text-white">Content</h2>
                        <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400" x-text="template.description"></p>
                    </x-slot>

                    <fieldset @disabled(! $canEdit) class="space-y-5">
                        <template x-if="template.optional">
                            <div class="flex items-start justify-between gap-4 rounded-xl bg-slate-50 px-4 py-3 dark:bg-slate-800/60">
                                <div>
                                    <p class="text-sm font-medium text-slate-800 dark:text-slate-100">Send this email</p>
                                    <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">
                                        Switch off if you don't want people to get it. Security events are still recorded in the activity log.
                                    </p>
                                </div>
                                <input type="hidden" name="enabled" x-bind:value="enabled ? '1' : '0'" />
                                <button
                                    type="button"
                                    role="switch"
                                    aria-label="Send this email"
                                    x-bind:aria-checked="enabled.toString()"
                                    x-on:click="enabled = ! enabled"
                                    x-bind:class="enabled ? 'bg-blue-600 dark:bg-blue-500' : 'bg-slate-300 dark:bg-slate-600'"
                                    class="relative mt-0.5 inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full transition-colors focus:outline-none focus-visible:ring-3 focus-visible:ring-blue-500/30 disabled:cursor-not-allowed disabled:opacity-60"
                                >
                                    <span
                                        x-bind:class="enabled ? 'translate-x-5' : 'translate-x-0'"
                                        class="absolute top-0.5 left-0.5 h-5 w-5 rounded-full bg-white shadow-sm transition-transform"
                                    ></span>
                                </button>
                            </div>
                        </template>

                        @foreach ($fieldLabels as $field => $label)
                            <template x-if="has({{ Js::from($field) }})">
                                <div>
                                    @if (in_array($field, ['body', 'note'], true))
                                        <x-admin.form.textarea
                                            :name="$field"
                                            :id="'template-' . $field"
                                            :label="$label"
                                            :rows="$field === 'body' ? 8 : 4"
                                            :error="$templateErrors->first($field)"
                                            :required="$field === 'body'"
                                            value=""
                                            x-model="values.{{ $field }}"
                                            x-on:focus="activeField = {{ Js::from($field) }}"
                                            data-field="{{ $field }}"
                                            maxlength="{{ $field === 'body' ? 5000 : 2000 }}"
                                        />
                                        <p class="mt-1.5 text-xs text-slate-500 dark:text-slate-400" x-text="template.hints.{{ $field }}"></p>
                                    @else
                                        <x-admin.form.input
                                            :name="$field"
                                            :id="'template-' . $field"
                                            :label="$label"
                                            :hint="$fieldHints[$field]"
                                            :error="$templateErrors->first($field)"
                                            :required="in_array($field, ['subject', 'button'], true)"
                                            x-model="values.{{ $field }}"
                                            x-on:focus="activeField = {{ Js::from($field) }}"
                                            data-field="{{ $field }}"
                                            maxlength="{{ $field === 'button' ? 60 : 200 }}"
                                        />
                                    @endif

                                    @if ($canEdit)
                                        <button
                                            type="button"
                                            x-show="values.{{ $field }} !== template.defaults.{{ $field }}"
                                            x-on:click="useDefault({{ Js::from($field) }})"
                                            class="mt-1.5 cursor-pointer text-xs font-medium text-slate-500 hover:text-blue-600 dark:text-slate-400"
                                        >
                                            Use original {{ strtolower($label) }}
                                        </button>
                                    @endif
                                </div>
                            </template>
                        @endforeach
                    </fieldset>

                    <div class="mt-6 border-t border-slate-100 pt-5 dark:border-slate-800">
                        <p class="text-sm font-medium text-slate-800 dark:text-slate-100">Placeholders</p>
                        <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">
                            Click one to add it where your cursor is. Each is replaced with the real value when the email is sent.
                        </p>
                        <ul class="mt-3 grid grid-cols-1 gap-1.5 sm:grid-cols-2">
                            <template x-for="placeholder in template.placeholders" :key="placeholder.name">
                                <li>
                                    <button
                                        type="button"
                                        x-on:click="insert(placeholder.name)"
                                        @disabled(! $canEdit)
                                        class="flex w-full cursor-pointer items-center gap-2 rounded-lg border border-slate-200 px-2.5 py-1.5 text-left transition hover:border-blue-300 hover:bg-blue-50/60 disabled:cursor-default disabled:hover:border-slate-200 disabled:hover:bg-transparent dark:border-slate-700 dark:hover:border-blue-500/40 dark:hover:bg-blue-500/10"
                                    >
                                        <code
                                            class="shrink-0 rounded bg-slate-100 px-1.5 py-0.5 font-mono text-[11px] text-slate-700 dark:bg-slate-800 dark:text-slate-200"
                                            x-text="'{' + placeholder.name + '}'"
                                        ></code>
                                        <span class="truncate text-xs text-slate-500 dark:text-slate-400" x-text="placeholder.label"></span>
                                    </button>
                                </li>
                            </template>
                        </ul>
                    </div>
                </x-admin.card>

                <x-admin.card icon="eye" class="2xl:sticky 2xl:top-4">
                    <x-slot:title>
                        <h2 class="text-sm font-semibold text-slate-900 dark:text-white">Preview</h2>
                        <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">Live, with example details</p>
                    </x-slot>
                    <x-slot:actions>
                        <div class="flex rounded-lg border border-slate-200 p-0.5 dark:border-slate-700" role="group" aria-label="Preview size">
                            <button
                                type="button"
                                x-on:click="device = 'desktop'"
                                x-bind:aria-pressed="(device === 'desktop').toString()"
                                aria-label="Desktop"
                                class="cursor-pointer rounded-md p-1.5 text-slate-400 transition aria-pressed:bg-slate-100 aria-pressed:text-slate-900 dark:aria-pressed:bg-slate-800 dark:aria-pressed:text-white"
                            >
                                <x-admin.icon name="monitor" class="h-4 w-4" />
                            </button>
                            <button
                                type="button"
                                x-on:click="device = 'mobile'"
                                x-bind:aria-pressed="(device === 'mobile').toString()"
                                aria-label="Phone"
                                class="cursor-pointer rounded-md p-1.5 text-slate-400 transition aria-pressed:bg-slate-100 aria-pressed:text-slate-900 dark:aria-pressed:bg-slate-800 dark:aria-pressed:text-white"
                            >
                                <x-admin.icon name="smartphone" class="h-4 w-4" />
                            </button>
                        </div>
                    </x-slot>

                    <template x-if="! enabled">
                        <p
                            class="mb-4 flex items-start gap-2 rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-800 dark:bg-amber-500/10 dark:text-amber-200"
                        >
                            <x-admin.icon name="alert-triangle" class="mt-px h-3.5 w-3.5 shrink-0" />
                            This email is switched off, so nobody gets it.
                        </p>
                    </template>

                    <template x-if="unknown.length">
                        <p
                            class="mb-4 flex items-start gap-2 rounded-lg bg-red-50 px-3 py-2 text-xs text-red-700 dark:bg-red-500/10 dark:text-red-300"
                        >
                            <x-admin.icon name="alert-circle" class="mt-px h-3.5 w-3.5 shrink-0" />
                            <span>
                                <span x-text="unknown.map((name) => '{' + name + '}').join(', ')"></span>
                                can't be filled in for this email. Pick one from the placeholder list.
                            </span>
                        </p>
                    </template>

                    <div class="mb-3 rounded-lg border border-slate-200 px-3 py-2 text-sm dark:border-slate-700">
                        <span class="text-slate-500 dark:text-slate-400">Subject:</span>
                        <span class="font-medium text-slate-900 dark:text-white" x-text="subject || '…'"></span>
                    </div>

                    <div class="relative overflow-hidden rounded-lg border border-slate-200 bg-slate-100 dark:border-slate-700">
                        <div x-show="loading && ! html" class="flex h-64 items-center justify-center text-sm text-slate-500">
                            <x-admin.icon name="refresh" class="mr-2 h-4 w-4 animate-spin" />
                            Loading preview…
                        </div>
                        <div x-show="failed" x-cloak class="px-4 py-10 text-center text-sm text-red-600">
                            The preview could not load. Check your connection.
                        </div>
                        <div x-show="html" class="mx-auto transition-all" x-bind:class="device === 'mobile' ? 'max-w-[375px]' : 'max-w-full'">
                            <iframe
                                title="Email preview"
                                sandbox="allow-same-origin allow-popups"
                                x-bind:srcdoc="html"
                                x-on:load="fit($el)"
                                class="block min-h-64 w-full bg-slate-100"
                            ></iframe>
                        </div>
                    </div>
                </x-admin.card>
            </div>
        </form>
    </template>

    @if ($canEdit)
        <x-modal name="restore-email-template" maxWidth="md">
            <div class="p-6">
                <div class="flex items-start gap-4">
                    <span
                        class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-blue-50 text-blue-600 ring-8 ring-blue-50/60 dark:bg-blue-500/10 dark:text-blue-400 dark:ring-blue-500/5"
                    >
                        <x-admin.icon name="history" class="h-5 w-5" />
                    </span>
                    <div class="min-w-0 pt-0.5">
                        <h2 class="text-base font-semibold text-slate-900 dark:text-white">Restore the original email?</h2>
                        <p class="mt-1.5 text-sm leading-relaxed text-slate-500 dark:text-slate-400">
                            Your changes to this email are replaced with the original text, and it is switched back on.
                        </p>
                    </div>
                </div>

                <div class="mt-6 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                    <x-admin.button type="button" variant="secondary" x-on:click="$dispatch('close-modal', 'restore-email-template')">
                        Cancel
                    </x-admin.button>
                    <form
                        method="POST"
                        x-data
                        x-on:submit="
                            $el.action =
                                {{ Js::from(route('admin.settings.email-templates.destroy', '__KEY__')) }}.replace(
                                    '__KEY__',
                                    new URL(window.location).searchParams.get('template'),
                                )
                            window.skipUnsavedCheck = true
                        "
                    >
                        @csrf
                        @method('DELETE')
                        <x-admin.button type="submit" icon="history">Restore original</x-admin.button>
                    </form>
                </div>
            </div>
        </x-modal>
    @endif
</div>
