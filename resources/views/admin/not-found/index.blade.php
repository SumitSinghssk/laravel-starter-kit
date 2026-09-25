@php
    use App\Models\Redirect;

    $canManage = auth()
        ->user()
        ->can('admin.not-found.manage');
    $canRedirect = auth()
        ->user()
        ->can('admin.redirects.create');
    $pageIds = $logs->pluck('id')->all();
    $keepDays = config('not_found.keep_days');

    $headers = ['URL', 'Hits', 'Last seen', 'Came from'];
    if ($canManage || $canRedirect) {
        $headers[] = 'Actions';
    }
    if ($canManage) {
        array_unshift($headers, '');
    }

    $tabs = collect([
        'open' => ['Needs attention', 'alert-triangle'],
        'redirected' => ['Redirected', 'redirect'],
        'ignored' => ['Ignored', 'eye-off'],
        'all' => ['All', 'list'],
    ])
        ->map(
            fn ($tab, $key) => [
                'label' => $tab[0],
                'icon' => $tab[1],
                'count' => $counts[$key],
                'url' => route('admin.not-found.index', array_filter(['status' => $key === 'open' ? null : $key, 'sort' => request('sort'), 'search' => request('search')])),
                'active' => $status === $key,
            ],
        )
        ->values()
        ->all();

    $reopen = $errors->hasAny(['source_path', 'target_url', 'status_code']);
@endphp

<x-admin :breadcrumb="[['label' => '404 Errors', 'url' => route('admin.not-found.index')]]">
    <div
        x-data="{
            selected: [],
            pageIds: @js($pageIds),
            get allSelected() {
                return this.pageIds.length && this.pageIds.every((id) => this.selected.includes(id))
            },
            toggleAll() {
                this.selected = this.allSelected ? [] : [...this.pageIds]
            },
            bulk(formId) {
                const form = document.getElementById(formId)
                form.querySelectorAll('input[name=\'ids[]\']').forEach((input) => input.remove())
                for (const id of this.selected) {
                    const input = document.createElement('input')
                    input.type = 'hidden'
                    input.name = 'ids[]'
                    input.value = id
                    form.appendChild(input)
                }
                form.submit()
            },
            redirectFrom(path) {
                window.dispatchEvent(new CustomEvent('prefill-redirect', { detail: { path } }))
                this.$dispatch('open-modal', 'create-redirect')
            },
        }"
        @if ($reopen) x-init="$nextTick(() => $dispatch('open-modal', 'create-redirect'))" @endif
    >
        <x-admin.page-header
            title="404 Errors"
            description="Pages visitors asked for that don't exist. Fix the popular ones with a redirect."
            icon="alert-triangle"
            :count="$counts['open']"
        >
            <x-slot:actions>
                <x-admin.transfer-actions type="not-found" />
                @if ($canManage && $logs->total())
                    <x-admin.delete-button
                        :route="route('admin.not-found.clear', ['status' => $status])"
                        modal-name="clear-not-found"
                        title="Clear this list?"
                        message="Every entry in this list is removed. URLs come back if they are visited again."
                    />
                @endif
            </x-slot>
        </x-admin.page-header>

        <x-admin.tabs label="404 lists" :tabs="$tabs" />

        <x-admin.table
            :headers="$headers"
            :data="$logs"
            :emptyMessage="$status === 'open' ? 'No broken links right now' : 'Nothing here'"
            :emptyText="$status === 'open' ? 'When visitors open a page that doesn\'t exist, it shows up here.' : null"
            emptyIcon="check-circle"
        >
            <x-slot:toolbar>
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <x-admin.filter.bar
                        :action="route('admin.not-found.index')"
                        :keep="['status' => $status === 'open' ? null : $status]"
                        search="Search URLs or referrers…"
                        class="min-w-0 flex-1"
                    >
                        <x-admin.filter.select
                            name="sort"
                            label="Sort"
                            icon="sliders"
                            :options="\App\Http\Controllers\Admin\NotFoundController::SORTS"
                        />
                    </x-admin.filter.bar>

                    @if ($canManage && $logs->count())
                        <label class="inline-flex cursor-pointer items-center gap-2 text-xs font-medium text-slate-500 dark:text-slate-400">
                            <input
                                type="checkbox"
                                x-bind:checked="allSelected"
                                x-on:change="toggleAll()"
                                class="h-4 w-4 cursor-pointer rounded border-slate-300 text-blue-600 focus:ring-blue-500/30 dark:border-slate-600 dark:bg-slate-800"
                            />
                            Select page
                        </label>
                    @endif

                    @if ($canManage)
                        <div class="flex items-center gap-2" x-show="selected.length" x-cloak x-transition.opacity>
                            <span class="tabular text-sm text-slate-600 dark:text-slate-300" x-text="`${selected.length} selected`"></span>
                            @if ($status === 'ignored')
                                <x-admin.button type="button" size="sm" variant="secondary" icon="eye" x-on:click="bulk('not-found-unignore')">
                                    Stop ignoring
                                </x-admin.button>
                            @else
                                <x-admin.button type="button" size="sm" variant="secondary" icon="eye-off" x-on:click="bulk('not-found-ignore')">
                                    Ignore
                                </x-admin.button>
                            @endif
                            <x-admin.button type="button" size="sm" variant="danger-outline" icon="trash" x-on:click="bulk('not-found-delete')">
                                Remove
                            </x-admin.button>
                        </div>
                    @endif
                </div>
            </x-slot>

            @foreach ($logs as $log)
                @php($isRedirected = isset($redirected[$log->path]))
                <tr x-bind:class="selected.includes({{ $log->id }}) && 'bg-blue-50/50 dark:bg-blue-500/5'">
                    @if ($canManage)
                        <td class="w-10">
                            <input
                                type="checkbox"
                                value="{{ $log->id }}"
                                x-model.number="selected"
                                class="h-4 w-4 cursor-pointer rounded border-slate-300 text-blue-600 focus:ring-blue-500/30 dark:border-slate-600 dark:bg-slate-800"
                                aria-label="Select {{ $log->path }}"
                            />
                        </td>
                    @endif

                    <td class="max-w-md">
                        <div class="flex min-w-0 items-center gap-2">
                            <a
                                href="{{ url($log->path) }}"
                                target="_blank"
                                rel="noopener"
                                class="truncate font-mono text-[13px] font-medium text-slate-900 hover:text-blue-600 dark:text-white dark:hover:text-blue-400"
                                title="Open {{ $log->path }}"
                            >
                                {{ $log->path }}
                            </a>
                            @if ($isRedirected)
                                <span
                                    class="shrink-0 rounded bg-emerald-50 px-1.5 py-px text-[10px] font-semibold text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300"
                                >
                                    Redirected
                                </span>
                            @elseif ($log->ignored)
                                <span
                                    class="shrink-0 rounded bg-slate-100 px-1.5 py-px text-[10px] font-semibold text-slate-500 dark:bg-slate-800 dark:text-slate-400"
                                >
                                    Ignored
                                </span>
                            @endif
                        </div>
                    </td>

                    <td class="tabular whitespace-nowrap">
                        <span
                            @class(['font-semibold', 'text-amber-600 dark:text-amber-400' => $log->hits >= 10 && ! $isRedirected, 'text-slate-700 dark:text-slate-200' => $log->hits < 10 || $isRedirected])
                        >
                            {{ number_format($log->hits) }}
                        </span>
                    </td>

                    <td class="whitespace-nowrap">
                        <span class="block text-slate-700 dark:text-slate-200" title="{{ local_datetime($log->last_seen_at) }}">
                            {{ $log->last_seen_at?->diffForHumans() }}
                        </span>
                        <span class="text-xs text-slate-400">first {{ local_date($log->first_seen_at) }}</span>
                    </td>

                    <td class="max-w-56">
                        @if ($log->referrer_label)
                            <a
                                href="{{ $log->last_referrer }}"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="flex min-w-0 items-center gap-1 text-sm text-slate-600 hover:text-blue-600 dark:text-slate-300 dark:hover:text-blue-400"
                                title="{{ $log->last_referrer }}"
                            >
                                <x-admin.icon :name="$log->isInternalReferrer() ? 'link' : 'globe'" class="h-3.5 w-3.5 shrink-0 text-slate-400" />
                                <span class="truncate">{{ $log->referrer_label }}</span>
                            </a>
                            @if ($log->isInternalReferrer())
                                <span class="text-[11px] text-amber-600 dark:text-amber-400">Broken link on your site</span>
                            @endif
                        @else
                            <span class="text-slate-400">Typed or bookmarked</span>
                        @endif
                    </td>

                    @if ($canManage || $canRedirect)
                        <td class="whitespace-nowrap">
                            <div class="flex items-center justify-end gap-1.5">
                                @if ($canRedirect && ! $isRedirected)
                                    <x-admin.button
                                        type="button"
                                        size="sm"
                                        variant="secondary"
                                        icon="redirect"
                                        x-on:click="redirectFrom({{ \Illuminate\Support\Js::from($log->path) }})"
                                    >
                                        Create redirect
                                    </x-admin.button>
                                @endif

                                @if ($canManage)
                                    <x-admin.dropdown width="w-48">
                                        <x-slot:trigger>
                                            <x-admin.button
                                                type="button"
                                                size="sm"
                                                variant="ghost"
                                                icon-only
                                                icon="more-vertical"
                                                aria-label="More actions"
                                                title="More actions"
                                            />
                                        </x-slot>

                                        <form method="POST" action="{{ route('admin.not-found.ignore') }}">
                                            @csrf
                                            <input type="hidden" name="ids[]" value="{{ $log->id }}" />
                                            <input type="hidden" name="ignore" value="{{ $log->ignored ? 0 : 1 }}" />
                                            <button
                                                type="submit"
                                                role="menuitem"
                                                class="flex w-full cursor-pointer items-center gap-2.5 px-3 py-2 text-left text-sm text-slate-700 hover:bg-slate-50 dark:text-slate-200 dark:hover:bg-slate-800"
                                            >
                                                <x-admin.icon :name="$log->ignored ? 'eye' : 'eye-off'" class="h-4 w-4 text-slate-400" />
                                                {{ $log->ignored ? 'Stop ignoring' : 'Ignore' }}
                                            </button>
                                        </form>
                                        <form method="POST" action="{{ route('admin.not-found.destroy') }}">
                                            @csrf
                                            @method('DELETE')
                                            <input type="hidden" name="ids[]" value="{{ $log->id }}" />
                                            <button
                                                type="submit"
                                                role="menuitem"
                                                class="flex w-full cursor-pointer items-center gap-2.5 px-3 py-2 text-left text-sm text-red-600 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-500/10"
                                            >
                                                <x-admin.icon name="trash" class="h-4 w-4" />
                                                Remove
                                            </button>
                                        </form>
                                    </x-admin.dropdown>
                                @endif
                            </div>
                        </td>
                    @endif
                </tr>
            @endforeach
        </x-admin.table>

        <p class="mt-3 flex items-start gap-2 text-xs text-slate-500 dark:text-slate-400">
            <x-admin.icon name="info" class="mt-px h-3.5 w-3.5 shrink-0" />
            Admin pages and common bot probes (WordPress, .env, .php files) aren't logged. Entries not seen for {{ $keepDays }} days are removed
            automatically.
        </p>

        @if ($canManage)
            <form id="not-found-ignore" method="POST" action="{{ route('admin.not-found.ignore') }}" class="hidden">
                @csrf
                <input type="hidden" name="ignore" value="1" />
            </form>
            <form id="not-found-unignore" method="POST" action="{{ route('admin.not-found.ignore') }}" class="hidden">
                @csrf
                <input type="hidden" name="ignore" value="0" />
            </form>
            <form id="not-found-delete" method="POST" action="{{ route('admin.not-found.destroy') }}" class="hidden">
                @csrf
                @method('DELETE')
            </form>
        @endif

        @if ($canRedirect)
            <x-modal name="create-redirect" maxWidth="lg">
                <form
                    method="POST"
                    action="{{ route('admin.not-found.redirect') }}"
                    x-data="{ source: @js(old('source_path', '')), submitting: false }"
                    x-on:prefill-redirect.window="
                        source = $event.detail.path
                        $nextTick(() => document.getElementById('target_url')?.focus())
                    "
                    x-on:submit="submitting = true"
                    class="space-y-5 p-6"
                >
                    @csrf
                    <input type="hidden" name="status" value="active" />

                    <div class="flex items-start gap-3">
                        <span
                            class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-blue-50 text-blue-600 dark:bg-blue-500/10 dark:text-blue-400"
                        >
                            <x-admin.icon name="redirect" class="h-5 w-5" />
                        </span>
                        <div>
                            <h2 class="text-base font-semibold text-slate-900 dark:text-white">Create a redirect</h2>
                            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Visitors to the broken URL are sent to a page that exists.</p>
                        </div>
                    </div>

                    <x-admin.form.input name="source_path" label="Broken URL" x-model="source" required>
                        <x-slot:leftIcon>
                            <x-admin.icon name="link" class="h-4 w-4" />
                        </x-slot>
                    </x-admin.form.input>

                    <x-admin.form.input
                        name="target_url"
                        id="target_url"
                        label="Send visitors to"
                        placeholder="/new-page or https://example.com/page"
                        required
                        hint="A path on this site or a full URL."
                    >
                        <x-slot:leftIcon>
                            <x-admin.icon name="arrow-right" class="h-4 w-4" />
                        </x-slot>
                    </x-admin.form.input>

                    <x-admin.form.select
                        name="status_code"
                        label="Redirect type"
                        :options="Redirect::STATUS_CODES"
                        :value="(string) old('status_code', 301)"
                        hint="Use 301 unless the move is temporary."
                    />

                    <div class="flex justify-end gap-2 border-t border-slate-100 pt-4 dark:border-slate-800">
                        <x-admin.button type="button" variant="secondary" x-on:click="$dispatch('close-modal', 'create-redirect')">
                            Cancel
                        </x-admin.button>
                        <x-admin.button icon="check">
                            <span x-text="submitting ? 'Creating…' : 'Create redirect'">Create redirect</span>
                        </x-admin.button>
                    </div>
                </form>
            </x-modal>
        @endif
    </div>
</x-admin>
