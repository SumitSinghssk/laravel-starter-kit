@php
    $control = \App\Support\FormField::controlClasses(false, ! $canUpdate);
@endphp

<x-admin.card
    id="settings-addresses"
    class="scroll-mt-24"
    title="Addresses"
    text="Office or store locations, with an optional Google Maps embed."
    icon="map-pin"
>
    @if ($canUpdate)
        <x-slot:actions>
            <x-admin.button type="button" variant="secondary" size="sm" icon="plus" x-on:click="addAddress()">Add address</x-admin.button>
        </x-slot>
    @endif

    <div class="grid grid-cols-1 gap-4 xl:grid-cols-2">
        <template x-for="(address, index) in addresses" :key="index">
            <div class="flex flex-col overflow-hidden rounded-xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
                <div
                    class="flex items-center justify-between gap-2 border-b border-slate-100 bg-slate-50/70 px-4 py-2.5 dark:border-slate-800 dark:bg-slate-800/40"
                >
                    <p class="flex min-w-0 items-center gap-2 text-sm font-medium text-slate-800 dark:text-slate-200">
                        <x-admin.icon name="building" class="h-4 w-4 text-slate-400" />
                        <span class="truncate" x-text="address.label || 'Location ' + (index + 1)"></span>
                    </p>
                    @if ($canUpdate)
                        <button
                            type="button"
                            x-on:click="removeAddress(index)"
                            aria-label="Remove address"
                            title="Remove address"
                            class="flex h-7 w-7 shrink-0 cursor-pointer items-center justify-center rounded-md text-slate-400 transition hover:bg-red-50 hover:text-red-600 focus:outline-none focus-visible:ring-3 focus-visible:ring-red-500/30 dark:hover:bg-red-500/10 dark:hover:text-red-400"
                        >
                            <x-admin.icon name="trash" class="h-4 w-4" />
                        </button>
                    @endif
                </div>

                <div class="flex-1 space-y-4 p-4">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label :for="'address-label-' + index" class="mb-1.5 block text-[13px] font-medium text-slate-700 dark:text-slate-200">
                                Label
                            </label>
                            <input
                                type="text"
                                :id="'address-label-' + index"
                                x-model="address.label"
                                :name="'addresses['+index+'][label]'"
                                :disabled="!canUpdate"
                                class="{{ $control }} px-3 py-2"
                                placeholder="e.g. Head Office"
                            />
                        </div>

                        <div>
                            <label :for="'address-text-' + index" class="mb-1.5 block text-[13px] font-medium text-slate-700 dark:text-slate-200">
                                Address
                            </label>
                            <input
                                type="text"
                                :id="'address-text-' + index"
                                x-model="address.text"
                                :name="'addresses['+index+'][text]'"
                                :disabled="!canUpdate"
                                class="{{ $control }} px-3 py-2"
                                placeholder="Street, City..."
                            />
                        </div>
                    </div>

                    <div>
                        <label :for="'address-map-' + index" class="mb-1.5 block text-[13px] font-medium text-slate-700 dark:text-slate-200">
                            Google Maps embed code
                        </label>
                        <textarea
                            :id="'address-map-' + index"
                            x-model="address.map_iframe"
                            :name="'addresses['+index+'][map_iframe]'"
                            :disabled="!canUpdate"
                            rows="2"
                            class="{{ $control }} resize-y px-3 py-2 font-mono text-xs leading-snug"
                            placeholder="Paste <iframe> code here..."
                        ></textarea>
                        <p class="mt-1.5 text-xs text-slate-500 dark:text-slate-400">Google Maps → Share → Embed a map → copy HTML.</p>
                    </div>
                </div>

                <div class="h-56 w-full border-t border-slate-100 bg-slate-50 dark:border-slate-800 dark:bg-slate-800/40">
                    <template x-if="address.map_iframe && address.map_iframe.includes('<iframe')">
                        <div x-html="address.map_iframe" class="h-full w-full [&_iframe]:h-full [&_iframe]:w-full [&_iframe]:border-0"></div>
                    </template>

                    <template x-if="! address.map_iframe || ! address.map_iframe.includes('<iframe')">
                        <div class="flex h-full w-full flex-col items-center justify-center gap-1.5 text-center">
                            <x-admin.icon name="map-pin" class="h-6 w-6 text-slate-300 dark:text-slate-600" />
                            <p class="text-xs text-slate-400">No map preview</p>
                        </div>
                    </template>
                </div>
            </div>
        </template>

        <template x-if="addresses.length === 0">
            <div
                class="flex flex-col items-center gap-3 rounded-xl border-2 border-dashed border-slate-200 px-4 py-10 text-center xl:col-span-2 dark:border-slate-800"
            >
                <span
                    class="flex h-11 w-11 items-center justify-center rounded-xl border border-slate-200 bg-slate-50 text-slate-400 dark:border-slate-700 dark:bg-slate-800"
                >
                    <x-admin.icon name="map-pin" class="h-5 w-5" />
                </span>
                <div>
                    <p class="text-sm font-medium text-slate-700 dark:text-slate-300">No addresses added yet</p>
                    <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">Locations you add appear on the contact page and footer.</p>
                </div>
                @if ($canUpdate)
                    <x-admin.button type="button" variant="secondary" size="sm" icon="plus" x-on:click="addAddress()">Add a location</x-admin.button>
                @endif
            </div>
        </template>
    </div>
</x-admin.card>
