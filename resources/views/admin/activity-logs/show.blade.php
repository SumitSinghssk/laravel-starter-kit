@php
    $actionConfig = [
        'login' => ['Login', 'success', 'log-in'],
        'logout' => ['Logout', 'neutral', 'log-out'],
        'failed_login' => ['Failed login', 'danger', 'x-circle'],
        'password_reset_requested' => ['Reset link sent', 'info', 'key'],
        'password_reset' => ['Password reset', 'warning', 'key'],
        'two_factor_enabled' => ['2FA turned on', 'success', 'shield-check'],
        'two_factor_changed' => ['2FA moved to new app', 'info', 'shield-check'],
        'two_factor_disabled' => ['2FA turned off', 'warning', 'shield'],
        'two_factor_reset' => ['2FA reset', 'warning', 'shield'],
        'two_factor_codes' => ['New recovery codes', 'info', 'key'],
        'two_factor_recovery' => ['Recovery code used', 'warning', 'key'],
        'two_factor_failed' => ['Wrong 2FA code', 'danger', 'x-circle'],
        'viewed' => ['Viewed', 'info', 'eye'],
        'created' => ['Created', 'success', 'plus'],
        'updated' => ['Updated', 'warning', 'pencil'],
        'deleted' => ['Deleted', 'danger', 'trash'],
    ];

    [$label, $tone, $icon] = $actionConfig[$log->action] ?? [ucfirst(str_replace('_', ' ', (string) $log->action)), 'neutral', 'circle'];

    $toneTile = [
        'success' => 'bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-300',
        'neutral' => 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300',
        'info' => 'bg-blue-50 text-blue-600 dark:bg-blue-500/10 dark:text-blue-300',
        'warning' => 'bg-amber-50 text-amber-600 dark:bg-amber-500/10 dark:text-amber-300',
        'danger' => 'bg-red-50 text-red-600 dark:bg-red-500/10 dark:text-red-300',
    ][$tone];

    $isSession = in_array($log->action, ['login', 'logout', 'failed_login']);
    $isCrud = in_array($log->action, ['created', 'updated', 'deleted']);

    $deviceIcon = match ($log->device_type) {
        'mobile' => 'smartphone',
        'tablet' => 'tablet',
        default => 'monitor',
    };

    $renderVal = function ($val): string {
        if ($val === null) {
            return '—';
        }
        if (is_array($val) || is_object($val)) {
            return json_encode($val, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        }
        if (is_string($val)) {
            $decoded = json_decode($val, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            }
        }
        return (string) $val;
    };

    $oldArr = is_array($log->old_values) ? $log->old_values : [];
    $newArr = is_array($log->new_values) ? $log->new_values : [];
    $diffKeys = array_unique(array_merge(array_keys($oldArr), array_keys($newArr)));

    $methodColors = [
        'GET' => 'bg-blue-50 text-blue-700 ring-blue-600/15 dark:bg-blue-500/10 dark:text-blue-300 dark:ring-blue-400/20',
        'POST' => 'bg-emerald-50 text-emerald-700 ring-emerald-600/15 dark:bg-emerald-500/10 dark:text-emerald-300 dark:ring-emerald-400/20',
        'PUT' => 'bg-amber-50 text-amber-700 ring-amber-600/20 dark:bg-amber-500/10 dark:text-amber-300 dark:ring-amber-400/20',
        'PATCH' => 'bg-amber-50 text-amber-700 ring-amber-600/20 dark:bg-amber-500/10 dark:text-amber-300 dark:ring-amber-400/20',
        'DELETE' => 'bg-red-50 text-red-700 ring-red-600/15 dark:bg-red-500/10 dark:text-red-300 dark:ring-red-400/20',
    ];

    $row = function (string $label, $value = null, bool $mono = false): string {
        $value = $value === null || $value === '' ? '—' : (string) $value;
        $valueClass = $mono ? 'font-mono text-xs' : 'text-sm';

        return '<div class="flex items-start justify-between gap-4 py-2.5">' .
            '<dt class="shrink-0 text-sm text-slate-500 dark:text-slate-400">' .
            e($label) .
            '</dt>' .
            '<dd class="' .
            $valueClass .
            ' min-w-0 text-right font-medium wrap-break-word text-slate-900 dark:text-white">' .
            e($value) .
            '</dd>' .
            '</div>';
    };
    $dl = 'divide-y divide-slate-100 dark:divide-slate-800';
@endphp

<x-admin
    :breadcrumb="[
        ['label' => 'Activity Logs',  'url' => route('admin.activity-logs.index')],
        ['label' => 'Log #' . $log->id, 'url' => '#'],
    ]"
>
    <x-admin.page-header
        :title="'Log #' . $log->id"
        :description="$log->description ?? ($log->page_title ?? ($log->model_name ?? 'Activity log entry'))"
        :back="route('admin.activity-logs.index')"
    />

    <div
        class="mb-4 flex flex-col gap-4 rounded-xl border border-slate-200/80 bg-white p-4 shadow-xs sm:flex-row sm:items-center sm:p-5 dark:border-slate-800 dark:bg-slate-900"
    >
        <div class="flex min-w-0 flex-1 items-center gap-3.5">
            <span class="{{ $toneTile }} flex h-11 w-11 shrink-0 items-center justify-center rounded-xl">
                <x-admin.icon :name="$icon" class="h-5 w-5" />
            </span>
            <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-2">
                    <h2 class="text-base font-semibold text-slate-900 dark:text-white">{{ $label }}</h2>
                    @if ($log->is_suspicious)
                        <x-admin.status-badge tone="danger" label="Suspicious activity" />
                    @endif
                </div>
                <p class="mt-0.5 flex items-center gap-1.5 truncate text-sm text-slate-500 dark:text-slate-400">
                    <x-admin.icon name="user" class="h-3.5 w-3.5 shrink-0" />
                    <span class="truncate">{{ $log->user?->name ?? 'Guest' }}</span>
                </p>
            </div>
        </div>

        <div class="flex flex-wrap gap-x-6 gap-y-2 text-sm sm:justify-end">
            <span class="flex items-center gap-1.5 text-slate-600 dark:text-slate-300">
                <x-admin.icon name="clock" class="h-4 w-4 text-slate-400" />
                {{ $log->created_at_formatted }}
            </span>
            @if ($log->ip_address)
                <span class="flex items-center gap-1.5 font-mono text-xs text-slate-600 dark:text-slate-300">
                    <x-admin.icon name="wifi" class="h-4 w-4 text-slate-400" />
                    {{ $log->ip_address }}
                </span>
            @endif
        </div>
    </div>

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-12">
        <div class="min-w-0 space-y-4 lg:col-span-8">
            <x-admin.card title="User" icon="user-circle" body-class="!py-1.5">
                <dl class="{{ $dl }}">
                    {!! $row('Name', $log->user?->name ?? 'Guest / Unknown') !!}
                    {!! $row('Email', $log->user?->email ?? $log->email) !!}
                    {!! $row('User ID', $log->user_id ? '#' . $log->user_id : null) !!}
                </dl>
            </x-admin.card>

            <x-admin.card title="Request" icon="globe" body-class="!py-1.5">
                <dl class="{{ $dl }}">
                    {!! $row('Page title', $log->page_title) !!}
                    <div class="flex items-start justify-between gap-4 py-2.5">
                        <dt class="shrink-0 text-sm text-slate-500 dark:text-slate-400">URL</dt>
                        <dd class="min-w-0 text-right font-mono text-xs break-all text-slate-900 dark:text-white">{{ $log->url ?? '—' }}</dd>
                    </div>
                    <div class="flex items-center justify-between gap-4 py-2.5">
                        <dt class="shrink-0 text-sm text-slate-500 dark:text-slate-400">Method</dt>
                        <dd>
                            @if ($log->method)
                                <span
                                    class="{{ $methodColors[$log->method] ?? 'bg-slate-50 text-slate-600 ring-slate-500/15 dark:bg-slate-800 dark:text-slate-300 dark:ring-slate-600/40' }} rounded-md px-1.5 py-0.5 font-mono text-xs font-medium ring-1 ring-inset"
                                >
                                    {{ $log->method }}
                                </span>
                            @else
                                <span class="text-sm text-slate-400">—</span>
                            @endif
                        </dd>
                    </div>
                    {!! $row('IP address', $log->ip_address, true) !!}
                    {!! $row('Description', $log->description) !!}
                </dl>
            </x-admin.card>

            @if ($isCrud)
                <x-admin.card title="Changes" icon="pencil">
                    <dl class="{{ $dl }} -my-2.5">
                        {!! $row('Model', $log->model_type ? class_basename($log->model_type) : null) !!}
                        {!! $row('Model ID', $log->model_id ? '#' . $log->model_id : null) !!}
                        {!! $row('Name', $log->model_name) !!}
                    </dl>

                    @if ($log->old_values || $log->new_values)
                        <div class="mt-5">
                            @if (empty($diffKeys))
                                <p class="text-sm text-slate-400">No changes recorded.</p>
                            @else
                                <div class="overflow-x-auto rounded-lg border border-slate-200 dark:border-slate-700">
                                    <table class="w-full text-sm">
                                        <thead>
                                            <tr class="border-b border-slate-200 bg-slate-50 dark:border-slate-700 dark:bg-slate-800/60">
                                                <th class="px-3.5 py-2 text-left text-xs font-medium text-slate-500 dark:text-slate-400">Field</th>
                                                <th class="px-3.5 py-2 text-left text-xs font-medium text-red-600 dark:text-red-400">
                                                    <span class="inline-flex items-center gap-1">
                                                        <x-admin.icon name="minus" class="h-3 w-3" />
                                                        Before
                                                    </span>
                                                </th>
                                                <th class="px-3.5 py-2 text-left text-xs font-medium text-emerald-600 dark:text-emerald-400">
                                                    <span class="inline-flex items-center gap-1">
                                                        <x-admin.icon name="plus" class="h-3 w-3" />
                                                        After
                                                    </span>
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                            @foreach ($diffKeys as $key)
                                                @php
                                                    $oldVal = $oldArr[$key] ?? null;
                                                    $newVal = $newArr[$key] ?? null;
                                                    $changed = json_encode($oldVal) !== json_encode($newVal);
                                                    $oldStr = $renderVal($oldVal);
                                                    $newStr = $renderVal($newVal);
                                                    $isMulti = str_contains($oldStr, "\n") || str_contains($newStr, "\n");
                                                @endphp

                                                <tr class="{{ $changed ? '' : 'opacity-50' }}">
                                                    <td
                                                        class="px-3.5 py-2.5 align-top font-medium whitespace-nowrap text-slate-700 dark:text-slate-300"
                                                    >
                                                        {{ ucfirst(str_replace('_', ' ', $key)) }}
                                                    </td>
                                                    <td class="px-3.5 py-2.5 align-top">
                                                        <div
                                                            class="max-w-70 rounded-md bg-red-50 px-2 py-1.5 text-red-700 dark:bg-red-500/10 dark:text-red-300"
                                                        >
                                                            @if ($isMulti)
                                                                <pre
                                                                    class="max-h-40 overflow-auto font-mono text-[11px] leading-tight whitespace-pre-wrap"
                                                                >
{{ $oldStr }}</pre
                                                                >
                                                            @else
                                                                <span class="text-xs wrap-break-word">{{ $oldStr }}</span>
                                                            @endif
                                                        </div>
                                                    </td>
                                                    <td class="px-3.5 py-2.5 align-top">
                                                        <div
                                                            class="max-w-70 rounded-md bg-emerald-50 px-2 py-1.5 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300"
                                                        >
                                                            @if ($isMulti)
                                                                <pre
                                                                    class="max-h-40 overflow-auto font-mono text-[11px] leading-tight whitespace-pre-wrap"
                                                                >
{{ $newStr }}</pre
                                                                >
                                                            @else
                                                                <span class="text-xs wrap-break-word">{{ $newStr }}</span>
                                                            @endif
                                                        </div>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @endif
                        </div>
                    @else
                        <p class="mt-4 text-sm text-slate-400">No change data recorded.</p>
                    @endif
                </x-admin.card>
            @endif

            @if ($isSession && ($log->login_at || $log->logout_at))
                <x-admin.card title="Session" icon="clock" body-class="!py-1.5">
                    <dl class="{{ $dl }}">
                        {!! $row('Login at', $log->login_at?->format('Y-m-d H:i:s')) !!}
                        {!! $row('Logout at', $log->logout_at?->format('Y-m-d H:i:s') ?? 'Still active') !!}
                        {!! $row('Duration', $log->session_duration_formatted) !!}
                        <div class="flex items-start justify-between gap-4 py-2.5">
                            <dt class="shrink-0 text-sm text-slate-500 dark:text-slate-400">Session ID</dt>
                            <dd class="min-w-0 truncate font-mono text-xs text-slate-900 dark:text-white">{{ $log->session_id ?? '—' }}</dd>
                        </div>
                    </dl>
                </x-admin.card>
            @endif
        </div>

        <div class="min-w-0 space-y-4 lg:col-span-4">
            @if ($log->is_suspicious)
                <div class="rounded-xl border border-red-200 bg-red-50 p-4 dark:border-red-500/30 dark:bg-red-500/10">
                    <div class="flex items-center gap-2">
                        <x-admin.icon name="alert-triangle" class="h-4 w-4 text-red-600 dark:text-red-400" />
                        <p class="text-sm font-semibold text-red-700 dark:text-red-300">Security alert</p>
                    </div>
                    <p class="mt-1.5 text-sm text-red-600 dark:text-red-300/90">
                        This login originated from an IP address never used before by this user. It may indicate unauthorized access.
                    </p>
                </div>
            @endif

            <x-admin.card title="Timestamp" icon="calendar" body-class="!py-1.5">
                <dl class="{{ $dl }}">
                    {!! $row('Logged at', $log->created_at_formatted) !!}
                    <div class="flex items-center justify-between gap-4 py-2.5">
                        <dt class="shrink-0 text-sm text-slate-500 dark:text-slate-400">Security</dt>
                        <dd>
                            @if ($log->is_suspicious)
                                <span class="inline-flex items-center gap-1 text-sm font-medium text-red-600 dark:text-red-400">
                                    <x-admin.icon name="alert-triangle" class="h-3.5 w-3.5" />
                                    Suspicious
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1 text-sm font-medium text-emerald-600 dark:text-emerald-400">
                                    <x-admin.icon name="shield-check" class="h-3.5 w-3.5" />
                                    Normal
                                </span>
                            @endif
                        </dd>
                    </div>
                </dl>
            </x-admin.card>

            @if ($isSession)
                <x-admin.card title="Device" :icon="$deviceIcon" body-class="!py-1.5">
                    <dl class="{{ $dl }}">
                        {!! $row('Device', ucfirst((string) ($log->device ?? $log->device_type))) !!}
                        {!! $row('Browser', collect([$log->browser, $log->browser_version])->filter()->implode(' '),) !!}
                        {!! $row('Platform', $log->platform) !!}
                    </dl>
                    @if ($log->user_agent)
                        <p
                            class="border-t border-slate-100 py-2.5 font-mono text-[11px] leading-relaxed break-all text-slate-400 dark:border-slate-800"
                        >
                            {{ $log->user_agent }}
                        </p>
                    @endif
                </x-admin.card>

                <x-admin.card title="Location" icon="map-pin" body-class="!py-1.5">
                    <dl class="{{ $dl }}">
                        {!! $row('City', $log->city) !!}
                        {!! $row('Region', $log->region) !!}
                        {!! $row('Country', $log->country) !!}
                        {!! $row('Timezone', $log->timezone) !!}
                        {!! $row('ISP', $log->isp) !!}
                    </dl>
                    @if ($log->latitude && $log->longitude)
                        <div class="pt-1 pb-2.5">
                            <x-admin.button
                                variant="secondary"
                                size="sm"
                                icon="map-pin"
                                full
                                :href="'https://maps.google.com/?q=' . $log->latitude . ',' . $log->longitude"
                                target="_blank"
                                rel="noopener noreferrer"
                            >
                                View on Google Maps
                            </x-admin.button>
                        </div>
                    @endif
                </x-admin.card>
            @endif
        </div>
    </div>
</x-admin>
