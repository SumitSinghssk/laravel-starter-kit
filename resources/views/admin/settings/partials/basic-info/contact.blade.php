<x-admin.card
    id="settings-contact"
    class="scroll-mt-24"
    title="Contact"
    text="Phone numbers and email addresses customers can reach you on."
    icon="phone"
>
    <div class="space-y-6">
        <div>
            <div class="mb-3 flex items-center justify-between gap-3">
                <div class="min-w-0">
                    <p class="text-sm font-medium text-slate-800 dark:text-slate-200">Phone numbers</p>
                    <p class="text-xs text-slate-500 dark:text-slate-400">Add one or more contact numbers.</p>
                </div>
                @if ($canUpdate)
                    <x-admin.button type="button" variant="secondary" size="sm" icon="plus" x-on:click="addPhone()">Add phone</x-admin.button>
                @endif
            </div>

            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <template x-for="(phone, index) in phones" :key="index">
                    <div class="flex items-center gap-2">
                        <div class="relative flex-1">
                            <x-admin.form.input
                                type="tel"
                                name="phones[]"
                                :id="false"
                                x-model="phones[index]"
                                x-bind:aria-label="'Phone number ' + (index + 1)"
                                placeholder="e.g. +1 234 567 8900"
                                :disabled="! $canUpdate"
                            >
                                <x-slot:leftIcon>
                                    <x-admin.icon name="phone" class="h-4 w-4" />
                                </x-slot>
                            </x-admin.form.input>
                        </div>
                        @if ($canUpdate)
                            <button
                                type="button"
                                x-on:click="removePhone(index)"
                                aria-label="Remove phone number"
                                title="Remove"
                                class="flex h-9 w-9 shrink-0 cursor-pointer items-center justify-center rounded-lg text-slate-400 transition hover:bg-red-50 hover:text-red-600 focus:outline-none focus-visible:ring-3 focus-visible:ring-red-500/30 dark:hover:bg-red-500/10 dark:hover:text-red-400"
                            >
                                <x-admin.icon name="x" class="h-4 w-4" />
                            </button>
                        @endif
                    </div>
                </template>
            </div>

            <p
                x-show="phones.length === 0"
                x-cloak
                class="rounded-lg border border-dashed border-slate-200 px-3 py-4 text-center text-xs text-slate-500 dark:border-slate-800 dark:text-slate-400"
            >
                No phone numbers yet.
            </p>
        </div>

        <div class="border-t border-slate-100 pt-6 dark:border-slate-800">
            <div class="mb-3 flex items-center justify-between gap-3">
                <div class="min-w-0">
                    <p class="text-sm font-medium text-slate-800 dark:text-slate-200">Email addresses</p>
                    <p class="text-xs text-slate-500 dark:text-slate-400">Primary and secondary contact emails.</p>
                </div>
                @if ($canUpdate)
                    <x-admin.button type="button" variant="secondary" size="sm" icon="plus" x-on:click="addEmail()">Add email</x-admin.button>
                @endif
            </div>

            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <template x-for="(email, index) in emails" :key="index">
                    <div class="flex items-center gap-2">
                        <div class="relative flex-1">
                            <x-admin.form.input
                                type="email"
                                name="emails[]"
                                :id="false"
                                x-model="emails[index]"
                                x-bind:aria-label="'Email address ' + (index + 1)"
                                placeholder="e.g. hello@domain.com"
                                :disabled="! $canUpdate"
                            >
                                <x-slot:leftIcon>
                                    <x-admin.icon name="mail" class="h-4 w-4" />
                                </x-slot>
                            </x-admin.form.input>
                        </div>
                        @if ($canUpdate)
                            <button
                                type="button"
                                x-on:click="removeEmail(index)"
                                aria-label="Remove email"
                                title="Remove"
                                class="flex h-9 w-9 shrink-0 cursor-pointer items-center justify-center rounded-lg text-slate-400 transition hover:bg-red-50 hover:text-red-600 focus:outline-none focus-visible:ring-3 focus-visible:ring-red-500/30 dark:hover:bg-red-500/10 dark:hover:text-red-400"
                            >
                                <x-admin.icon name="x" class="h-4 w-4" />
                            </button>
                        @endif
                    </div>
                </template>
            </div>

            <p
                x-show="emails.length === 0"
                x-cloak
                class="rounded-lg border border-dashed border-slate-200 px-3 py-4 text-center text-xs text-slate-500 dark:border-slate-800 dark:text-slate-400"
            >
                No email addresses yet.
            </p>
        </div>
    </div>
</x-admin.card>
