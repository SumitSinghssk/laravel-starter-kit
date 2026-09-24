@php
    use Illuminate\Support\Js;

    $tabs = collect($menus)
        ->map(
            fn ($m, $key) => [
                'label' => $m['name'],
                'icon' => $key === 'footer' ? 'layers' : 'panel-left',
                'url' => route('admin.menus.edit', $key),
                'active' => $menu->key === $key,
            ],
        )
        ->values()
        ->all();

    $isFooter = $menu->key === 'footer';
    $field = 'h-9 w-full rounded-lg border bg-white px-3 text-sm text-slate-900 shadow-xs outline-none focus:border-blue-400 focus:ring-3 focus:ring-blue-500/20 dark:bg-slate-900 dark:text-white';
    $check = 'h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500/30 dark:border-slate-600 dark:bg-slate-800';
@endphp

<x-admin :breadcrumb="[['label' => 'Menus', 'url' => route('admin.menus.index')], ['label' => $menu->name]]">
    <div
        x-data="menuBuilder(
                    {{
                        Js::from([
                            'rows' => $rows,
                            'urls' => ['update' => route('admin.menus.update', $menu->key), 'upload' => route('admin.images.store')],
                            'maxDepth' => $config['depth'],
                            'canEdit' => $canEdit,
                            'rich' => (bool) ($config['rich'] ?? false),
                            'siteUrl' => url('/'),
                            'version' => $version,
                        ])
                    }},
                )"
    >
        <x-admin.page-header title="Menus" description="Create the links in your website's header and footer, and drag them into order." icon="menu">
            <x-slot:actions>
                <x-admin.button variant="secondary" icon="external-link" :href="url('/')" target="_blank" rel="noopener">
                    View website
                </x-admin.button>
            </x-slot>
        </x-admin.page-header>

        <x-admin.tabs label="Menus" :tabs="$tabs" />

        <div
            x-show="stale"
            x-cloak
            class="mb-5 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-100"
        >
            <p class="flex items-start gap-2">
                <x-admin.icon name="alert-triangle" class="mt-0.5 h-4 w-4 shrink-0" />
                <span>
                    This menu was changed somewhere else (another tab or another admin), so your changes weren't saved. Reload to see the latest
                    version, then make your changes again.
                </span>
            </p>
            <x-admin.button type="button" size="sm" variant="secondary" icon="refresh" x-on:click="reloadLatest()">Reload</x-admin.button>
        </div>

        @unless ($canEdit)
            <div
                class="mb-5 flex items-start gap-2.5 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-300"
            >
                <x-admin.icon name="lock" class="mt-0.5 h-4 w-4 shrink-0" />
                <span>You can look at the menus, but you don't have permission to change them.</span>
            </div>
        @endunless

        <div class="grid grid-cols-1 items-start gap-6 xl:grid-cols-[21rem_minmax(0,1fr)]">
            @if ($canEdit)
                <aside class="xl:sticky xl:top-4">
                    <x-admin.card title="Add a link" text="It goes to the end of the menu. Drag it where you want it." icon="plus">
                        <form class="space-y-4" x-on:submit.prevent="addLink()">
                            <div>
                                <label for="menu-link-label" class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-200">Label</label>
                                <input
                                    id="menu-link-label"
                                    x-ref="linkLabel"
                                    type="text"
                                    x-model="link.label"
                                    maxlength="80"
                                    placeholder="e.g. About us"
                                    class="{{ $field }} border-slate-200 dark:border-slate-700"
                                />
                            </div>
                            <div>
                                <label
                                    for="menu-link-url"
                                    class="mb-1.5 flex items-baseline justify-between text-sm font-medium text-slate-700 dark:text-slate-200"
                                >
                                    URL
                                    <span class="text-xs font-normal text-slate-400">optional</span>
                                </label>
                                <input
                                    id="menu-link-url"
                                    type="text"
                                    x-model="link.url"
                                    placeholder="https://… or /about"
                                    spellcheck="false"
                                    autocomplete="off"
                                    class="{{ $field }} border-slate-200 font-mono text-xs dark:border-slate-700"
                                />
                                <p class="mt-1.5 text-xs text-slate-500 dark:text-slate-400">
                                    Paste a web address, or a path on this site like
                                    <span class="font-mono">/contact</span>
                                    . Also #section, mailto: and tel:.
                                </p>
                                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                                    Leave it empty for a
                                    <strong class="font-medium text-slate-700 dark:text-slate-200">title</strong>
                                    , like “{{ $isFooter ? 'Company' : 'Services' }}”, then nest links under it.
                                    {{ $isFooter ? 'It shows as a column heading.' : 'Clicking it opens its dropdown.' }}
                                </p>
                                <p class="mt-1 text-xs text-red-600 dark:text-red-400" x-show="linkError" x-cloak x-text="linkError"></p>
                            </div>
                            <label
                                class="flex cursor-pointer items-center gap-2 text-sm text-slate-700 dark:text-slate-200"
                                x-show="link.url.trim()"
                                x-cloak
                            >
                                <input type="checkbox" x-model="link.new_tab" class="{{ $check }}" />
                                Open in a new tab
                            </label>
                            <x-admin.button icon="plus" full>
                                <span x-text="link.url.trim() ? 'Add link' : 'Add title'">Add to menu</span>
                            </x-admin.button>
                        </form>
                    </x-admin.card>
                </aside>
            @endif

            <section class="{{ $canEdit ? '' : 'xl:col-span-2' }} min-w-0">
                <x-admin.card :title="$menu->name" :text="$config['hint']" icon="menu" :padded="false">
                    <x-slot:actions>
                        <span
                            class="tabular text-xs text-slate-500 dark:text-slate-400"
                            x-text="rows.length + (rows.length === 1 ? ' link' : ' links')"
                        ></span>
                    </x-slot>

                    <div class="p-3 sm:p-4">
                        <template x-if="! rows.length">
                            <div class="rounded-xl border border-dashed border-slate-300 px-4 py-12 text-center dark:border-slate-700">
                                <span
                                    class="mx-auto flex h-11 w-11 items-center justify-center rounded-xl border border-slate-200 text-slate-400 dark:border-slate-700"
                                >
                                    <x-admin.icon name="menu" class="h-5 w-5" />
                                </span>
                                <p class="mt-3 text-sm font-medium text-slate-800 dark:text-slate-100">This menu is empty</p>
                                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                                    Add links with the form. The website hides an empty menu.
                                </p>
                            </div>
                        </template>

                        <ol class="space-y-1.5" x-bind:class="drag && 'select-none'" aria-label="{{ $menu->name }} links">
                            <template x-for="(row, index) in rows" :key="row.uid">
                                <li x-bind:id="'menu-row-' + row.uid" x-show="! isHidden(index)" class="relative">
                                    <template x-for="(guide, level) in guides(index)" :key="level">
                                        <span
                                            class="pointer-events-none absolute top-0 -bottom-1.5 w-4"
                                            x-bind:style="{ left: level * 28 + 13 + 'px' }"
                                            aria-hidden="true"
                                        >
                                            <span
                                                x-show="guide === 'pipe' || guide === 'tee'"
                                                class="absolute inset-y-0 left-0 w-px bg-slate-300 dark:bg-slate-600"
                                            ></span>
                                            <span
                                                x-show="guide === 'tee'"
                                                class="absolute top-[26px] left-0 h-px w-[15px] bg-slate-300 dark:bg-slate-600"
                                            ></span>
                                            <span
                                                x-show="guide === 'elbow'"
                                                class="absolute top-0 left-0 h-[27px] w-[15px] rounded-bl-lg border-b border-l border-slate-300 dark:border-slate-600"
                                            ></span>
                                        </span>
                                    </template>

                                    <div
                                        x-show="dropLine(row.uid)"
                                        class="mb-1.5 h-1 rounded-full bg-blue-500"
                                        x-bind:style="{ marginLeft: (drag?.depth ?? 0) * 28 + 'px' }"
                                    ></div>

                                    <div
                                        class="rounded-xl border bg-white shadow-xs transition dark:bg-slate-900"
                                        x-bind:style="{ marginLeft: row.depth * 28 + 'px' }"
                                        x-bind:class="{
                                            'opacity-40': isDragging(row.uid),
                                            'ring-2 ring-blue-400/70': flash.includes(row.uid),
                                            'border-red-300 dark:border-red-500/50': rowHasError(index),
                                            'border-slate-200 dark:border-slate-700': ! rowHasError(index),
                                        }"
                                    >
                                        <div class="flex items-center gap-1.5 py-1.5 pr-1.5 pl-1">
                                            <button
                                                type="button"
                                                x-on:pointerdown="startDrag($event, index)"
                                                x-bind:disabled="! canEdit"
                                                class="flex h-8 w-7 shrink-0 cursor-grab touch-none items-center justify-center rounded-md text-slate-400 hover:bg-slate-100 hover:text-slate-700 active:cursor-grabbing disabled:cursor-default disabled:opacity-40 dark:hover:bg-slate-800 dark:hover:text-slate-200"
                                                aria-label="Drag to move. Drag right to nest."
                                                title="Drag to move · drag right to nest"
                                            >
                                                <x-admin.icon name="grip" class="h-4 w-4" />
                                            </button>

                                            <button
                                                type="button"
                                                x-on:click="toggleCollapse(index)"
                                                x-bind:disabled="! hasChildren(index)"
                                                x-bind:aria-label="
                                                    hasChildren(index)
                                                        ? row.collapsed
                                                            ? 'Show nested links'
                                                            : 'Hide nested links'
                                                        : 'Single link'
                                                "
                                                x-bind:title="
                                                    hasChildren(index)
                                                        ? row.collapsed
                                                            ? 'Show nested links'
                                                            : 'Hide nested links'
                                                        : ''
                                                "
                                                class="relative mr-1 flex h-8 w-8 shrink-0 cursor-pointer items-center justify-center rounded-lg transition disabled:cursor-default"
                                                x-bind:class="
                                                    hasChildren(index)
                                                        ? 'bg-amber-50 text-amber-600 hover:bg-amber-100 dark:bg-amber-500/10 dark:text-amber-400 dark:hover:bg-amber-500/20'
                                                        : 'bg-slate-100 text-slate-400 dark:bg-slate-800 dark:text-slate-500'
                                                "
                                            >
                                                <x-admin.icon name="folder" class="h-4 w-4" x-show="hasChildren(index)" />
                                                <x-admin.icon name="link" class="h-3.5 w-3.5" x-show="! hasChildren(index)" />
                                                <span
                                                    x-show="hasChildren(index)"
                                                    class="absolute -right-0.5 -bottom-0.5 flex h-3.5 w-3.5 items-center justify-center rounded-full bg-white text-slate-500 shadow-xs ring-1 ring-slate-200 dark:bg-slate-900 dark:ring-slate-700"
                                                >
                                                    <x-admin.icon
                                                        name="chevron-right"
                                                        class="h-2.5 w-2.5 transition-transform"
                                                        x-bind:class="! row.collapsed && 'rotate-90'"
                                                    />
                                                </span>
                                            </button>

                                            <button
                                                type="button"
                                                x-on:click="row.open = ! row.open"
                                                class="flex min-w-0 flex-1 cursor-pointer items-center gap-2.5 text-left"
                                                x-bind:aria-expanded="row.open.toString()"
                                            >
                                                <img
                                                    x-show="row.image && inMega(index)"
                                                    x-bind:src="imageSrc(row)"
                                                    alt=""
                                                    class="h-9 w-9 shrink-0 rounded-md object-cover ring-1 ring-slate-200 dark:ring-slate-700"
                                                    loading="lazy"
                                                />
                                                <span class="min-w-0">
                                                    <span class="flex items-center gap-2">
                                                        <span
                                                            class="truncate text-sm font-medium text-slate-900 dark:text-white"
                                                            x-bind:class="! row.is_active && 'text-slate-400 line-through dark:text-slate-500'"
                                                            x-text="row.label || 'Untitled link'"
                                                        ></span>
                                                        <span x-show="row.new_tab" class="shrink-0 text-slate-400" title="Opens in a new tab">
                                                            <x-admin.icon name="external-link" class="h-3.5 w-3.5" />
                                                        </span>
                                                        <span
                                                            x-show="role(index)"
                                                            x-text="role(index)"
                                                            class="shrink-0 rounded-md px-1.5 text-[10px] leading-4 font-semibold"
                                                            x-bind:class="{
                                                                'bg-violet-50 text-violet-700 dark:bg-violet-500/10 dark:text-violet-300': [
                                                                    'Mega menu',
                                                                    'Column',
                                                                    'Card',
                                                                ].includes(role(index)),
                                                                'bg-sky-50 text-sky-700 dark:bg-sky-500/10 dark:text-sky-300': [
                                                                    'Dropdown',
                                                                    'Side menu',
                                                                ].includes(role(index)),
                                                            }"
                                                        ></span>
                                                    </span>
                                                    <span class="flex min-w-0 items-center gap-1.5 text-[11px] text-slate-500 dark:text-slate-400">
                                                        <span x-show="! isTitle(row)" class="truncate font-mono" x-text="row.url"></span>
                                                        <span
                                                            x-show="isTitle(row)"
                                                            class="shrink-0 font-medium text-slate-500 dark:text-slate-400"
                                                        >
                                                            Title · no link
                                                        </span>
                                                        <span
                                                            x-show="row.description && inMega(index)"
                                                            class="truncate"
                                                            x-text="'· ' + row.description"
                                                        ></span>
                                                        @if ($isFooter)
                                                            <span x-show="hasChildren(index) && ! row.collapsed" class="shrink-0">
                                                                · column heading
                                                            </span>
                                                        @endif

                                                        <span
                                                            x-show="row.collapsed && hasChildren(index)"
                                                            class="shrink-0 font-medium text-amber-700 dark:text-amber-400"
                                                            x-text="
                                                                '· ' +
                                                                    descendants(index) +
                                                                    (descendants(index) === 1 ? ' nested link hidden' : ' nested links hidden')
                                                            "
                                                        ></span>
                                                    </span>
                                                </span>
                                            </button>

                                            <span
                                                x-show="! row.is_active"
                                                class="hidden shrink-0 rounded-full bg-slate-100 px-2 py-0.5 text-[10.5px] font-semibold text-slate-500 sm:inline dark:bg-slate-800 dark:text-slate-400"
                                            >
                                                Hidden
                                            </span>
                                            <span
                                                x-show="row.url && ! validUrl(row.url)"
                                                class="hidden shrink-0 rounded-full bg-red-50 px-2 py-0.5 text-[10.5px] font-semibold text-red-700 sm:inline dark:bg-red-500/10 dark:text-red-300"
                                            >
                                                Check URL
                                            </span>
                                            <span
                                                x-show="needsChildren(index)"
                                                class="hidden shrink-0 rounded-full bg-amber-50 px-2 py-0.5 text-[10.5px] font-semibold text-amber-700 sm:inline dark:bg-amber-500/10 dark:text-amber-300"
                                                title="A title needs links nested under it, or a URL"
                                            >
                                                Nest links under it
                                            </span>

                                            <template x-if="canEdit">
                                                <div class="flex shrink-0 items-center">
                                                    @foreach ([['moveUp(index)', 'chevron-up', 'Move up', 'canMoveUp(index)'], ['moveDown(index)', 'chevron-down', 'Move down', 'canMoveDown(index)'], ['outdent(index)', 'chevron-left', 'Move out a level', 'canOutdent(index)'], ['indent(index)', 'chevron-right', 'Nest under the link above', 'canIndent(index)']] as [$action, $icon, $label, $enabled])
                                                        <button
                                                            type="button"
                                                            x-on:click="{{ $action }}"
                                                            x-bind:disabled="! {{ $enabled }}"
                                                            class="hidden h-8 w-7 cursor-pointer items-center justify-center rounded-md text-slate-400 hover:bg-slate-100 hover:text-slate-700 disabled:pointer-events-none disabled:opacity-30 sm:flex dark:hover:bg-slate-800 dark:hover:text-slate-200"
                                                            aria-label="{{ $label }}"
                                                            title="{{ $label }}"
                                                        >
                                                            <x-admin.icon :name="$icon" class="h-4 w-4" />
                                                        </button>
                                                    @endforeach
                                                </div>
                                            </template>

                                            <button
                                                type="button"
                                                x-on:click="row.open = ! row.open"
                                                class="flex h-8 w-8 shrink-0 cursor-pointer items-center justify-center rounded-md text-slate-400 hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-slate-800 dark:hover:text-slate-200"
                                                aria-label="Edit link"
                                            >
                                                <x-admin.icon
                                                    name="chevron-down"
                                                    class="h-4 w-4 transition-transform"
                                                    x-bind:class="row.open && 'rotate-180'"
                                                />
                                            </button>
                                        </div>

                                        <div x-show="row.open" x-cloak class="border-t border-slate-100 px-4 py-4 dark:border-slate-800">
                                            <div class="grid gap-3 sm:grid-cols-2">
                                                <div>
                                                    <label
                                                        class="mb-1.5 block text-xs font-medium text-slate-600 dark:text-slate-300"
                                                        x-bind:for="'label-' + row.uid"
                                                    >
                                                        Label
                                                    </label>
                                                    <input
                                                        type="text"
                                                        x-bind:id="'label-' + row.uid"
                                                        x-model="row.label"
                                                        maxlength="80"
                                                        x-bind:readonly="! canEdit"
                                                        class="{{ $field }}"
                                                        x-bind:class="
                                                            error(index, 'label')
                                                                ? 'border-red-400 dark:border-red-500/60'
                                                                : 'border-slate-200 dark:border-slate-700'
                                                        "
                                                    />
                                                    <p
                                                        class="mt-1 text-xs text-red-600 dark:text-red-400"
                                                        x-show="error(index, 'label')"
                                                        x-text="error(index, 'label')"
                                                    ></p>
                                                </div>
                                                <div>
                                                    <label
                                                        class="mb-1.5 block text-xs font-medium text-slate-600 dark:text-slate-300"
                                                        x-bind:for="'url-' + row.uid"
                                                    >
                                                        URL
                                                    </label>
                                                    <input
                                                        type="text"
                                                        x-bind:id="'url-' + row.uid"
                                                        x-model="row.url"
                                                        x-bind:readonly="! canEdit"
                                                        spellcheck="false"
                                                        autocomplete="off"
                                                        class="{{ $field }} font-mono text-xs"
                                                        x-bind:class="
                                                            error(index, 'url') || (row.url && ! validUrl(row.url))
                                                                ? 'border-red-400 dark:border-red-500/60'
                                                                : 'border-slate-200 dark:border-slate-700'
                                                        "
                                                    />
                                                    <p
                                                        class="mt-1 text-xs text-red-600 dark:text-red-400"
                                                        x-show="error(index, 'url')"
                                                        x-text="error(index, 'url')"
                                                    ></p>
                                                    <p
                                                        class="mt-1 text-xs text-red-600 dark:text-red-400"
                                                        x-show="! error(index, 'url') && row.url && ! validUrl(row.url)"
                                                    >
                                                        Use a web address (https://…), a path like /about, #section, mailto: or tel:.
                                                    </p>
                                                    <p
                                                        class="mt-1 text-xs text-slate-500 dark:text-slate-400"
                                                        x-show="! error(index, 'url') && isTitle(row)"
                                                    >
                                                        Empty, so this is a title.
                                                        {{ $isFooter ? 'It shows as a column heading.' : 'Clicking it opens its dropdown.' }}
                                                    </p>
                                                </div>
                                            </div>

                                            @if ($config['rich'] ?? false)
                                                <template x-if="row.depth === 0">
                                                    <div class="mt-4">
                                                        <p class="mb-1.5 text-xs font-medium text-slate-600 dark:text-slate-300">Opens as</p>
                                                        <div class="grid gap-2 sm:grid-cols-2">
                                                            @foreach (['dropdown' => ['Dropdown', 'A simple list of links under it.'], 'mega' => ['Mega menu', 'A wide panel: level 2 are column headings, level 3 their links or product cards.']] as $style => [$styleLabel, $styleText])
                                                                <label
                                                                    class="flex cursor-pointer items-start gap-2.5 rounded-lg border p-3 transition"
                                                                    x-bind:class="
                                                                        row.style === {{ Js::from($style) }}
                                                                            ? 'border-blue-500 bg-blue-50/60 ring-1 ring-blue-500 dark:border-blue-400 dark:bg-blue-500/10'
                                                                            : 'border-slate-200 hover:border-slate-300 dark:border-slate-700'
                                                                    "
                                                                >
                                                                    <input
                                                                        type="radio"
                                                                        class="mt-0.5"
                                                                        value="{{ $style }}"
                                                                        x-model="row.style"
                                                                        x-bind:disabled="! canEdit"
                                                                    />
                                                                    <span>
                                                                        <span class="block text-sm font-medium text-slate-800 dark:text-slate-100">
                                                                            {{ $styleLabel }}
                                                                        </span>
                                                                        <span class="block text-xs text-slate-500 dark:text-slate-400">
                                                                            {{ $styleText }}
                                                                        </span>
                                                                    </span>
                                                                </label>
                                                            @endforeach
                                                        </div>
                                                    </div>
                                                </template>

                                                <template x-if="row.depth === 2 && inMega(index)">
                                                    <div class="mt-4 grid gap-3 sm:grid-cols-[auto_minmax(0,1fr)]">
                                                        <div class="flex flex-col items-center gap-1.5">
                                                            <div
                                                                class="media-checker flex h-24 w-20 items-center justify-center overflow-hidden rounded-lg ring-1 ring-slate-200 dark:ring-slate-700"
                                                            >
                                                                <img
                                                                    x-show="row.image"
                                                                    x-bind:src="imageSrc(row)"
                                                                    alt=""
                                                                    class="h-full w-full object-cover"
                                                                />
                                                                <x-admin.icon name="image" class="h-5 w-5 text-slate-400" x-show="! row.image" />
                                                            </div>
                                                            <template x-if="canEdit">
                                                                <label
                                                                    class="cursor-pointer text-xs font-semibold text-blue-600 hover:underline dark:text-blue-400"
                                                                >
                                                                    <span
                                                                        x-text="row.uploading ? 'Uploading…' : row.image ? 'Change' : 'Upload'"
                                                                    ></span>
                                                                    <input
                                                                        type="file"
                                                                        accept="image/jpeg,image/png,image/webp"
                                                                        class="sr-only"
                                                                        x-on:change="uploadImage(row, $event)"
                                                                        x-bind:disabled="row.uploading"
                                                                    />
                                                                </label>
                                                            </template>
                                                        </div>
                                                        <div class="space-y-3">
                                                            <div>
                                                                <label
                                                                    class="mb-1.5 block text-xs font-medium text-slate-600 dark:text-slate-300"
                                                                    x-bind:for="'image-' + row.uid"
                                                                >
                                                                    Card image
                                                                    <span class="font-normal text-slate-400">· upload, or paste an image URL</span>
                                                                </label>
                                                                <input
                                                                    type="text"
                                                                    x-bind:id="'image-' + row.uid"
                                                                    x-model="row.image"
                                                                    x-bind:readonly="! canEdit"
                                                                    spellcheck="false"
                                                                    autocomplete="off"
                                                                    placeholder="https://… or /storage/…"
                                                                    class="{{ $field }} border-slate-200 font-mono text-xs dark:border-slate-700"
                                                                />
                                                                <p
                                                                    class="mt-1 text-xs text-red-600 dark:text-red-400"
                                                                    x-show="error(index, 'image')"
                                                                    x-text="error(index, 'image')"
                                                                ></p>
                                                            </div>
                                                            <div>
                                                                <label
                                                                    class="mb-1.5 block text-xs font-medium text-slate-600 dark:text-slate-300"
                                                                    x-bind:for="'desc-' + row.uid"
                                                                >
                                                                    Caption
                                                                    <span class="font-normal text-slate-400">· e.g. a price</span>
                                                                </label>
                                                                <input
                                                                    type="text"
                                                                    x-bind:id="'desc-' + row.uid"
                                                                    x-model="row.description"
                                                                    maxlength="120"
                                                                    x-bind:readonly="! canEdit"
                                                                    placeholder="Rs 1299"
                                                                    class="{{ $field }} border-slate-200 dark:border-slate-700"
                                                                />
                                                                <p
                                                                    class="mt-1 text-xs text-red-600 dark:text-red-400"
                                                                    x-show="error(index, 'description')"
                                                                    x-text="error(index, 'description')"
                                                                ></p>
                                                            </div>
                                                            <p class="text-xs text-slate-500 dark:text-slate-400">
                                                                Links with an image show as product cards in the mega menu.
                                                            </p>
                                                        </div>
                                                    </div>
                                                </template>
                                            @endif

                                            <p
                                                class="mt-2 text-xs text-red-600 dark:text-red-400"
                                                x-show="error(index, 'depth')"
                                                x-text="error(index, 'depth')"
                                            ></p>

                                            <div
                                                class="mt-4 flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 pt-3 dark:border-slate-800"
                                            >
                                                <div class="flex flex-wrap items-center gap-x-5 gap-y-2">
                                                    <label class="flex cursor-pointer items-center gap-2 text-sm text-slate-700 dark:text-slate-200">
                                                        <input
                                                            type="checkbox"
                                                            x-model="row.is_active"
                                                            x-bind:disabled="! canEdit"
                                                            class="{{ $check }}"
                                                        />
                                                        Show on website
                                                    </label>
                                                    <label
                                                        class="flex cursor-pointer items-center gap-2 text-sm text-slate-700 dark:text-slate-200"
                                                        x-show="! isTitle(row)"
                                                    >
                                                        <input
                                                            type="checkbox"
                                                            x-model="row.new_tab"
                                                            x-bind:disabled="! canEdit"
                                                            class="{{ $check }}"
                                                        />
                                                        Open in a new tab
                                                    </label>
                                                </div>
                                                <template x-if="canEdit">
                                                    <button
                                                        type="button"
                                                        x-on:click="remove(index)"
                                                        class="inline-flex cursor-pointer items-center gap-1.5 text-xs font-semibold text-red-600 hover:underline dark:text-red-400"
                                                    >
                                                        <x-admin.icon name="trash" class="h-3.5 w-3.5" />
                                                        <span x-text="hasChildren(index) ? 'Remove (nested links move up)' : 'Remove'"></span>
                                                    </button>
                                                </template>
                                            </div>
                                        </div>
                                    </div>
                                </li>
                            </template>
                            <li
                                x-show="dropAtEnd"
                                x-cloak
                                class="h-1 rounded-full bg-blue-500"
                                x-bind:style="{ marginLeft: (drag?.depth ?? 0) * 28 + 'px' }"
                            ></li>
                        </ol>
                    </div>
                </x-admin.card>

                <p class="mt-3 flex items-start gap-2 px-1 text-xs text-slate-500 dark:text-slate-400">
                    <x-admin.icon name="info" class="mt-px h-3.5 w-3.5 shrink-0" />
                    <span>
                        Drag the handle to reorder; drag right to nest a link under the one above, up to three levels.
                        {{ $isFooter ? 'A top link with nested links becomes a column heading.' : 'Level 2 opens as a dropdown and level 3 opens to the side.' }}
                        Hidden links stay here but don't show on the website.
                    </span>
                </p>
            </section>
        </div>

        @if ($canEdit)
            <div
                class="sticky bottom-3 z-20 mt-6 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-200/80 bg-white/90 px-4 py-3 shadow-sm backdrop-blur-md dark:border-slate-800 dark:bg-slate-900/90"
            >
                <p class="flex min-w-0 items-center gap-2 text-sm">
                    <template x-if="errorCount">
                        <span class="flex items-center gap-1.5 font-medium text-red-600 dark:text-red-400">
                            <x-admin.icon name="alert-circle" class="h-4 w-4" />
                            <span x-text="errorCount + (errorCount === 1 ? ' link needs fixing' : ' links need fixing')"></span>
                        </span>
                    </template>
                    <template x-if="! errorCount && dirty">
                        <span class="flex items-center gap-1.5 font-medium text-amber-700 dark:text-amber-300">
                            <span class="h-2 w-2 rounded-full bg-amber-500"></span>
                            Unsaved changes
                        </span>
                    </template>
                    <template x-if="! errorCount && ! dirty">
                        <span class="flex items-center gap-1.5 text-slate-500 dark:text-slate-400">
                            <x-admin.icon name="check-circle" class="h-4 w-4 text-emerald-500" />
                            The website shows this menu.
                        </span>
                    </template>
                </p>
                <div class="flex items-center gap-2">
                    <x-admin.button type="button" variant="ghost" x-show="dirty" x-cloak x-on:click="discard()">Discard</x-admin.button>
                    <x-admin.button type="button" icon="save" x-on:click="save()" x-bind:disabled="saving || ! dirty">
                        <span x-text="saving ? 'Saving…' : 'Save menu'">Save menu</span>
                    </x-admin.button>
                </div>
            </div>
        @endif
    </div>
</x-admin>
