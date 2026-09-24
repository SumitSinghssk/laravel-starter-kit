@php
    $data = $enquiry->data ?? [];
    $scalar = fn ($key) => is_scalar($data[$key] ?? null) && (string) $data[$key] !== '' ? (string) $data[$key] : null;

    $name = $scalar('name');
    $email = $scalar('email');
    $phone = $scalar('phone');
    $company = $scalar('company');
    $message = $scalar('message');
    $ip = $scalar('ip') ?? ($scalar('ip_address') ?? null);

    $fields = collect($data)->except(['name', 'email', 'phone', 'company', 'message', 'ip', 'ip_address']);

    $initials = $name
        ? collect(preg_split('/\s+/', trim($name)))
            ->filter()
            ->take(2)
            ->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))
            ->implode('')
        : null;

    $modalName = 'enquiry-' . $enquiry->id;
    $linkClass = 'text-slate-700 hover:text-blue-600 dark:text-slate-200 dark:hover:text-blue-400';
@endphp

<x-modal name="{{ $modalName }}" maxWidth="2xl">
    <div class="bg-white dark:bg-slate-900">
        <div class="flex items-start gap-4 border-b border-slate-100 px-5 py-5 sm:px-6 dark:border-slate-800">
            <span
                class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-blue-50 text-base font-semibold text-blue-700 dark:bg-blue-500/10 dark:text-blue-300"
            >
                @if ($initials)
                    {{ $initials }}
                @else
                    <x-admin.icon name="user" class="h-5 w-5" />
                @endif
            </span>

            <div class="min-w-0 flex-1">
                <h2 class="truncate text-base font-semibold text-slate-900 dark:text-white">
                    {{ $name ?: ($email ?: ($phone ?: 'Anonymous')) }}
                </h2>

                <div class="mt-1.5 flex flex-wrap items-center gap-1.5">
                    <x-admin.status-badge :status="$enquiry->status" />
                    <span
                        class="inline-flex items-center gap-1 rounded-md border border-slate-200 bg-white px-1.5 py-0.5 text-xs font-medium whitespace-nowrap text-slate-600 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300"
                    >
                        <x-admin.icon name="globe" class="h-3 w-3 text-slate-400" />
                        {{ \Illuminate\Support\Str::headline((string) $enquiry->source) }}
                    </span>
                </div>
            </div>

            <button
                type="button"
                x-on:click="$dispatch('close-modal', '{{ $modalName }}')"
                aria-label="Close"
                class="-mt-1 -mr-2 flex h-8 w-8 shrink-0 cursor-pointer items-center justify-center rounded-lg text-slate-400 transition hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-slate-800 dark:hover:text-slate-200"
            >
                <x-admin.icon name="x" class="h-4.5 w-4.5" />
            </button>
        </div>

        <div class="max-h-[65vh] space-y-5 overflow-y-auto px-5 py-5 sm:px-6">
            @if ($email || $phone || $company)
                <ul class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                    @if ($email)
                        <li class="flex min-w-0 items-center gap-2.5 text-sm">
                            <span
                                class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg border border-slate-200 bg-slate-50 text-slate-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300"
                            >
                                <x-admin.icon name="mail" class="h-4 w-4" />
                            </span>
                            <span class="min-w-0">
                                <span class="block text-xs text-slate-500 dark:text-slate-400">Email</span>
                                <a href="mailto:{{ $email }}" class="{{ $linkClass }} block truncate font-medium">{{ $email }}</a>
                            </span>
                        </li>
                    @endif

                    @if ($phone)
                        <li class="flex min-w-0 items-center gap-2.5 text-sm">
                            <span
                                class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg border border-slate-200 bg-slate-50 text-slate-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300"
                            >
                                <x-admin.icon name="phone" class="h-4 w-4" />
                            </span>
                            <span class="min-w-0">
                                <span class="block text-xs text-slate-500 dark:text-slate-400">Phone</span>
                                <a href="tel:{{ preg_replace('/[^\d+]/', '', $phone) }}" class="{{ $linkClass }} block truncate font-medium">
                                    {{ $phone }}
                                </a>
                            </span>
                        </li>
                    @endif

                    @if ($company)
                        <li class="flex min-w-0 items-center gap-2.5 text-sm">
                            <span
                                class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg border border-slate-200 bg-slate-50 text-slate-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300"
                            >
                                <x-admin.icon name="building" class="h-4 w-4" />
                            </span>
                            <span class="min-w-0">
                                <span class="block text-xs text-slate-500 dark:text-slate-400">Company</span>
                                <span class="block truncate font-medium text-slate-700 dark:text-slate-200">{{ $company }}</span>
                            </span>
                        </li>
                    @endif
                </ul>
            @endif

            @if ($message)
                <div>
                    <p class="mb-1.5 flex items-center gap-1.5 text-xs font-medium text-slate-500 dark:text-slate-400">
                        <x-admin.icon name="message" class="h-3.5 w-3.5" />
                        Message
                    </p>
                    <div
                        class="rounded-lg border border-slate-200 bg-slate-50 p-4 text-sm leading-relaxed wrap-break-word whitespace-pre-line text-slate-700 dark:border-slate-700 dark:bg-slate-800/60 dark:text-slate-200"
                    >
                        {{ trim($message) }}
                    </div>
                </div>
            @endif

            @if ($fields->isNotEmpty())
                <div>
                    <p class="mb-1.5 flex items-center gap-1.5 text-xs font-medium text-slate-500 dark:text-slate-400">
                        <x-admin.icon name="list" class="h-3.5 w-3.5" />
                        Other details
                    </p>
                    <dl class="divide-y divide-slate-100 rounded-lg border border-slate-200 dark:divide-slate-800 dark:border-slate-700">
                        @foreach ($fields as $key => $value)
                            <div class="flex flex-col gap-0.5 px-3.5 py-2.5 sm:flex-row sm:gap-4">
                                <dt class="shrink-0 text-xs text-slate-500 sm:w-36 sm:pt-0.5 dark:text-slate-400">
                                    {{ ucfirst(str_replace(['_', '-'], ' ', $key)) }}
                                </dt>
                                <dd class="min-w-0 text-sm wrap-break-word text-slate-700 dark:text-slate-200">
                                    @if (is_array($value))
                                        <span class="font-mono text-xs text-slate-500">{{ json_encode($value) }}</span>
                                    @elseif (is_string($value) && filter_var($value, FILTER_VALIDATE_URL))
                                        <a href="{{ $value }}" target="_blank" rel="noopener" class="{{ $linkClass }} underline underline-offset-2">
                                            {{ $value }}
                                        </a>
                                    @elseif (is_string($value) && filter_var($value, FILTER_VALIDATE_EMAIL))
                                        <a href="mailto:{{ $value }}" class="{{ $linkClass }} underline underline-offset-2">{{ $value }}</a>
                                    @else
                                        {{ is_scalar($value) && $value !== '' ? $value : '—' }}
                                    @endif
                                </dd>
                            </div>
                        @endforeach
                    </dl>
                </div>
            @endif

            @if (! $email && ! $phone && ! $company && ! $message && $fields->isEmpty())
                <p class="text-sm text-slate-400 italic dark:text-slate-500">No data attached to this enquiry.</p>
            @endif

            <dl class="grid grid-cols-1 gap-x-6 gap-y-3 border-t border-slate-100 pt-4 text-sm sm:grid-cols-2 dark:border-slate-800">
                <div class="flex min-w-0 items-start gap-2">
                    <x-admin.icon name="calendar" class="mt-0.5 h-4 w-4 shrink-0 text-slate-400" />
                    <div class="min-w-0">
                        <dt class="text-xs text-slate-500 dark:text-slate-400">Received</dt>
                        <dd class="text-slate-700 dark:text-slate-200">
                            {{ $enquiry->created_at->format('d M Y, H:i') }}
                            <span class="text-xs text-slate-400">· {{ $enquiry->created_at->diffForHumans() }}</span>
                        </dd>
                    </div>
                </div>

                <div class="flex min-w-0 items-start gap-2">
                    <x-admin.icon name="eye" class="mt-0.5 h-4 w-4 shrink-0 text-slate-400" />
                    <div class="min-w-0">
                        <dt class="text-xs text-slate-500 dark:text-slate-400">Seen</dt>
                        <dd class="text-slate-700 dark:text-slate-200">
                            @if ($enquiry->seen_at)
                                {{ $enquiry->seen_at->diffForHumans() }}
                                @if ($enquiry->seenBy)
                                    <span class="text-slate-500 dark:text-slate-400">by</span>
                                    <span class="font-medium">{{ $enquiry->seenBy->name }}</span>
                                @endif
                            @else
                                <span class="font-medium text-blue-600 dark:text-blue-400">Not seen yet</span>
                            @endif
                        </dd>
                    </div>
                </div>

                @if ($ip)
                    <div class="flex min-w-0 items-start gap-2">
                        <x-admin.icon name="wifi" class="mt-0.5 h-4 w-4 shrink-0 text-slate-400" />
                        <div class="min-w-0">
                            <dt class="text-xs text-slate-500 dark:text-slate-400">IP address</dt>
                            <dd class="font-mono text-xs text-slate-700 dark:text-slate-200">{{ $ip }}</dd>
                        </div>
                    </div>
                @endif

                @if ($enquiry->source_url && preg_match('#^https?://#i', $enquiry->source_url))
                    <div class="flex min-w-0 items-start gap-2 sm:col-span-2">
                        <x-admin.icon name="link" class="mt-0.5 h-4 w-4 shrink-0 text-slate-400" />
                        <div class="min-w-0">
                            <dt class="text-xs text-slate-500 dark:text-slate-400">Source page</dt>
                            <dd class="min-w-0">
                                <a
                                    href="{{ $enquiry->source_url }}"
                                    target="_blank"
                                    rel="noopener"
                                    class="{{ $linkClass }} inline-flex max-w-full items-center gap-1 break-all"
                                >
                                    {{ $enquiry->source_url }}
                                    <x-admin.icon name="external-link" class="h-3 w-3 shrink-0" />
                                </a>
                            </dd>
                        </div>
                    </div>
                @endif
            </dl>
        </div>

        <div
            class="flex flex-col-reverse gap-2 border-t border-slate-100 bg-slate-50/60 px-5 py-3.5 sm:flex-row sm:justify-end sm:px-6 dark:border-slate-800 dark:bg-slate-800/30"
        >
            <x-admin.button variant="secondary" type="button" x-on:click="$dispatch('close-modal', '{{ $modalName }}')">Close</x-admin.button>
            @if ($email)
                <x-admin.button :href="'mailto:' . $email" icon="send">Reply by email</x-admin.button>
            @endif
        </div>
    </div>
</x-modal>
