@php
    $initialTab = $errors->any() || request('tab') === 'details' ? 'details' : 'media';
@endphp

<x-admin :breadcrumb="[
    ['label' => 'Gallery', 'url' => route('admin.galleries.index')],
    ['label' => $gallery->title]
]">
    <x-admin.page-header
        :title="$gallery->title"
        :description="$gallery->event_date ? 'Event date: ' . local_day($gallery->event_date) : 'Photos, videos and YouTube links in this album.'"
        icon="image"
        :back="route('admin.galleries.index')"
    >
        @can('admin.galleries.delete')
            <x-slot:actions>
                <x-admin.delete-button
                    :route="route('admin.galleries.destroy', $gallery)"
                    title="Delete this album?"
                    message="The album and all of its photos and videos will be permanently deleted."
                />
            </x-slot>
        @endcan
    </x-admin.page-header>

    <div
        x-data="{
            tab: @js($initialTab),
            setTab(tab) {
                this.tab = tab
                const url = new URL(window.location)
                url.searchParams.set('tab', tab)
                window.history.replaceState({}, '', url)
            },
        }"
    >
        <nav class="mb-5 flex gap-1 border-b border-slate-200 dark:border-slate-800" aria-label="Album sections">
            @foreach (['media' => ['Media', 'image'], 'details' => ['Album details', 'settings']] as $id => [$label, $icon])
                <button
                    type="button"
                    x-on:click="setTab(@js($id))"
                    x-bind:aria-current="tab === @js($id) ? 'page' : null"
                    x-bind:class="
                        tab === @js($id)
                            ? 'border-blue-600 text-blue-700 dark:border-blue-400 dark:text-blue-300'
                            : 'border-transparent text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white'
                    "
                    class="-mb-px inline-flex cursor-pointer items-center gap-2 border-b-2 px-3 py-2.5 text-sm font-medium transition"
                >
                    <x-admin.icon :name="$icon" class="h-4 w-4" />
                    {{ $label }}
                    @if ($id === 'media')
                        <span
                            class="tabular rounded-full bg-slate-100 px-1.5 py-px text-[11px] font-semibold text-slate-600 dark:bg-slate-800 dark:text-slate-300"
                        >
                            {{ $gallery->items_count }}
                        </span>
                    @endif
                </button>
            @endforeach
        </nav>

        <div x-show="tab === 'media'" @if ($initialTab !== 'media') x-cloak @endif>
            @include('admin.galleries.partials.media-manager')
        </div>

        <div x-show="tab === 'details'" @if ($initialTab !== 'details') x-cloak @endif>
            <x-admin.form-page
                :header="false"
                :action="route('admin.galleries.update', $gallery)"
                method="PUT"
                title="Album details"
                :back="route('admin.galleries.index')"
                submit="Save changes"
                submitting="Saving…"
            >
                @include('admin.galleries.partials.form')
            </x-admin.form-page>
        </div>
    </div>
</x-admin>
