@php
    use App\Enums\EnquiryStatus;
    use App\Models\Enquiry;
    use App\Models\EnquiryActivity;
    use Illuminate\Support\Js;
    use Illuminate\Support\Str;

    $user = auth()->user();
    $canEdit = $user->can('admin.enquiries.edit');
    $canReply = $user->can('admin.enquiries.reply');
    $canDelete = $user->can('admin.enquiries.delete');

    $status = $enquiry->status_enum;
    $email = $enquiry->field('email');
    $phone = $enquiry->field('phone');
    $company = $enquiry->field('company');
    $subject = $enquiry->field('subject');
    $enquiryMessage = $enquiry->field('message');
    $validEmail = $email && filter_var($email, FILTER_VALIDATE_EMAIL);
    $whatsapp = $phone ? preg_replace('/\D+/', '', $phone) : null;
    $extraFields = collect($enquiry->data ?? [])
        ->except([...Enquiry::CONTACT_FIELDS, 'ip', 'ip_address'])
        ->filter(fn ($value) => $value !== null && $value !== '' && $value !== []);

    $firstName = Str::before($enquiry->field('name') ?? '', ' ') ?: 'there';
    $replySubject = old('subject', $subject ? 'Re: ' . $subject : 'Your enquiry to ' . \App\Helpers\Settings::appName());
    $replyBody = old('body', "Hi {$firstName},\n\n\n\nBest regards,\n{$user->name}\n" . \App\Helpers\Settings::appName());

    $needsNote = collect(EnquiryStatus::cases())
        ->filter(fn ($case) => $case->needsNote())
        ->map(fn ($case) => $case->value)
        ->values();
    $statusOptions = EnquiryStatus::options();
    $assigneeOptions = ['' => 'No one'] + $assignees->mapWithKeys(fn ($person) => [$person->id => $person->id === $user->id ? $person->name . ' (you)' : $person->name])->all();

    $who = fn ($activity) => $activity->user ? ($activity->user->id === $user->id ? 'You' : $activity->user->name) : 'System';
    $activityStyle = [
        EnquiryActivity::NOTE => ['note', 'bg-amber-50 text-amber-600 ring-amber-100 dark:bg-amber-500/10 dark:text-amber-300 dark:ring-amber-500/20'],
        EnquiryActivity::STATUS => ['circle-dot', 'bg-violet-50 text-violet-600 ring-violet-100 dark:bg-violet-500/10 dark:text-violet-300 dark:ring-violet-500/20'],
        EnquiryActivity::REPLY => ['send', 'bg-blue-50 text-blue-600 ring-blue-100 dark:bg-blue-500/10 dark:text-blue-300 dark:ring-blue-500/20'],
        EnquiryActivity::ASSIGNED => ['user-check', 'bg-slate-100 text-slate-600 ring-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:ring-slate-700'],
        EnquiryActivity::FOLLOW_UP => ['calendar', 'bg-slate-100 text-slate-600 ring-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:ring-slate-700'],
        EnquiryActivity::CREATED => ['plus', 'bg-emerald-50 text-emerald-600 ring-emerald-100 dark:bg-emerald-500/10 dark:text-emerald-300 dark:ring-emerald-500/20'],
        EnquiryActivity::EDITED => ['pencil', 'bg-slate-100 text-slate-600 ring-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:ring-slate-700'],
    ];
    $noteCount = $enquiry->activities->where('type', EnquiryActivity::NOTE)->count();
    $replyCount = $enquiry->activities->where('type', EnquiryActivity::REPLY)->count();

    $followUpTone = [
        'overdue' => 'text-red-600 dark:text-red-400',
        'today' => 'text-amber-600 dark:text-amber-400',
        'upcoming' => 'text-slate-700 dark:text-slate-200',
    ];
    $dl = 'flex items-start justify-between gap-4 py-2 text-sm';
@endphp

<x-admin :breadcrumb="[['label' => 'Enquiries', 'url' => route('admin.enquiries.index')], ['label' => $enquiry->reference]]">
    <x-admin.page-header
        :title="$enquiry->display_name"
        :description="$enquiry->reference . ' · Received ' . local_datetime($enquiry->created_at) . ' · ' . $enquiry->source_label"
        :back="route('admin.enquiries.index')"
    >
        <x-slot:actions>
            <div class="flex items-center gap-1">
                <x-admin.tooltip text="Newer enquiry">
                    <x-admin.button
                        variant="secondary"
                        icon-only
                        icon="chevron-left"
                        :href="$previous ? route('admin.enquiries.show', $previous) : null"
                        :disabled="! $previous"
                        aria-label="Newer enquiry"
                    />
                </x-admin.tooltip>
                <x-admin.tooltip text="Older enquiry">
                    <x-admin.button
                        variant="secondary"
                        icon-only
                        icon="chevron-right"
                        :href="$next ? route('admin.enquiries.show', $next) : null"
                        :disabled="! $next"
                        aria-label="Older enquiry"
                    />
                </x-admin.tooltip>
            </div>
            @if ($canEdit)
                <x-admin.button variant="secondary" icon="pencil" :href="route('admin.enquiries.edit', $enquiry)">Edit details</x-admin.button>
            @endif

            @if ($canDelete)
                <x-admin.delete-button
                    :route="route('admin.enquiries.destroy', $enquiry)"
                    title="Delete this enquiry?"
                    message="It will be moved to the Trash with its notes, where it can be restored."
                />
            @endif
        </x-slot>
    </x-admin.page-header>

    <x-admin.form-grid>
        <x-admin.card :title="$subject ?: 'Message'" icon="message" :text="$subject ? 'Message' : null">
            @if ($enquiryMessage)
                <div class="text-[15px] leading-relaxed whitespace-pre-line text-slate-800 dark:text-slate-100">{{ $enquiryMessage }}</div>
            @else
                <p class="text-sm text-slate-400">No message was written.</p>
            @endif

            @if ($extraFields->isNotEmpty() || $enquiry->source_url)
                <dl class="mt-5 grid grid-cols-1 gap-x-6 gap-y-3 border-t border-slate-100 pt-4 sm:grid-cols-2 dark:border-slate-800">
                    @foreach ($extraFields as $key => $fieldValue)
                        <div class="min-w-0">
                            <dt class="text-xs text-slate-500 dark:text-slate-400">{{ Str::headline($key) }}</dt>
                            <dd class="mt-0.5 text-sm wrap-break-word text-slate-800 dark:text-slate-100">
                                {{ is_scalar($fieldValue) ? $fieldValue : implode(', ', array_map(fn ($item) => is_scalar($item) ? $item : json_encode($item), (array) $fieldValue)) }}
                            </dd>
                        </div>
                    @endforeach

                    @if ($enquiry->source_url)
                        <div class="min-w-0 sm:col-span-2">
                            <dt class="text-xs text-slate-500 dark:text-slate-400">Sent from page</dt>
                            <dd class="mt-0.5 text-sm">
                                <a
                                    href="{{ $enquiry->source_url }}"
                                    target="_blank"
                                    rel="noopener"
                                    class="inline-flex max-w-full items-center gap-1 text-blue-600 hover:underline dark:text-blue-400"
                                >
                                    <span class="truncate">{{ $enquiry->source_url }}</span>
                                    <x-admin.icon name="external-link" class="h-3.5 w-3.5 shrink-0" />
                                </a>
                            </dd>
                        </div>
                    @endif
                </dl>
            @endif
        </x-admin.card>

        @if ($canReply)
            <x-admin.card
                id="reply"
                title="Reply by email"
                icon="send"
                class="scroll-mt-4"
                :text="$validEmail ? 'Goes to ' . $email . '. Their answer comes back to your inbox (' . $user->email . ').' : null"
            >
                @if (! $validEmail)
                    <p class="flex items-start gap-2 text-sm text-slate-500 dark:text-slate-400">
                        <x-admin.icon name="info" class="mt-0.5 h-4 w-4 shrink-0" />
                        <span>
                            There's no valid email address on this enquiry.

                            @if ($phone)
                                Call or message them on {{ $phone }} instead, then add a note about it below.
                            @elseif ($canEdit)
                                <a
                                    href="{{ route('admin.enquiries.edit', $enquiry) }}"
                                    class="font-medium text-blue-600 hover:underline dark:text-blue-400"
                                >
                                    Add an email address
                                </a>
                                to reply from here.
                            @endif
                        </span>
                    </p>
                @else
                    @unless ($canDeliver)
                        <p
                            class="mb-4 flex items-start gap-2 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2.5 text-sm text-amber-800 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-200"
                        >
                            <x-admin.icon name="alert-triangle" class="mt-0.5 h-4 w-4 shrink-0" />
                            <span>
                                Email isn't set up, so replies are only written to the log and won't reach them.
                                @can('admin.settings.email.view')
                                    <a href="{{ route('admin.settings.index', ['tab' => 'email']) }}" class="font-medium underline">Set up email</a>
                                @endcan
                            </span>
                        </p>
                    @endunless

                    <form
                        method="POST"
                        action="{{ route('admin.enquiries.reply', $enquiry) }}"
                        class="space-y-4"
                        x-data="{ submitting: false }"
                        x-on:submit="submitting = true"
                    >
                        @csrf
                        <x-admin.form.input
                            name="subject"
                            label="Subject"
                            :value="$replySubject"
                            required
                            :error="$errors->reply->first('subject')"
                        />
                        <x-admin.form.textarea
                            name="body"
                            label="Message"
                            :value="$replyBody"
                            rows="9"
                            required
                            :error="$errors->reply->first('body')"
                        />
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <x-admin.form.checkbox name="quote" label="Include their original message" :checked="old('quote', true)" />
                            <x-admin.button icon="send" x-bind:disabled="submitting">
                                <span x-text="submitting ? 'Sending…' : 'Send reply'">Send reply</span>
                            </x-admin.button>
                        </div>
                    </form>
                @endif
            </x-admin.card>
        @endif

        <x-admin.card
            id="activity"
            title="Activity"
            icon="history"
            class="scroll-mt-4"
            :text="$noteCount . ' ' . Str::plural('note', $noteCount) . ' · ' . $replyCount . ' ' . Str::plural('reply', $replyCount)"
        >
            @if ($canEdit)
                <form
                    method="POST"
                    action="{{ route('admin.enquiries.notes.store', $enquiry) }}"
                    class="mb-6"
                    x-data="{
                        submitting: false,
                        body: {{ Js::from(old('body_note', '')) }},
                    }"
                    x-on:submit="submitting = true"
                >
                    @csrf
                    <label for="note-body" class="sr-only">Add a note</label>
                    <div
                        class="rounded-xl border border-slate-200 bg-white shadow-xs transition focus-within:border-blue-400 focus-within:ring-3 focus-within:ring-blue-500/20 dark:border-slate-700 dark:bg-slate-900"
                    >
                        <textarea
                            id="note-body"
                            name="body"
                            rows="3"
                            x-model="body"
                            placeholder="Add a note: what you discussed, what happens next… Only your team sees notes."
                            class="block w-full resize-y rounded-t-xl border-0 bg-transparent px-3.5 py-3 text-sm text-slate-900 placeholder:text-slate-400 focus:ring-0 focus:outline-none dark:text-white"
                        ></textarea>
                        <div class="flex items-center justify-between gap-3 border-t border-slate-100 px-3 py-2 dark:border-slate-800">
                            <span class="text-xs text-slate-400">Visible to your team only</span>
                            <x-admin.button size="sm" icon="plus" x-bind:disabled="submitting || body.trim() === ''">Add note</x-admin.button>
                        </div>
                    </div>
                    @error('body')
                        <p class="mt-1.5 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </form>
            @endif

            <ol
                class="relative space-y-5 before:absolute before:top-2 before:bottom-2 before:left-4 before:w-px before:bg-slate-200 dark:before:bg-slate-800"
            >
                @foreach ($enquiry->activities as $activity)
                    @php
                        [$icon, $iconClass] = $activityStyle[$activity->type] ?? ['circle', $activityStyle[EnquiryActivity::EDITED][1]];
                        $meta = $activity->meta ?? [];
                    @endphp

                    <li class="relative flex gap-3" id="activity-{{ $activity->id }}">
                        <span
                            class="{{ $iconClass }} relative z-10 flex h-8 w-8 shrink-0 items-center justify-center rounded-full ring-4 ring-white dark:ring-slate-900"
                        >
                            <x-admin.icon :name="$icon" class="h-3.5 w-3.5" />
                        </span>

                        <div class="min-w-0 flex-1 pt-1">
                            <div class="flex flex-wrap items-baseline justify-between gap-x-3 gap-y-0.5">
                                <p class="text-sm text-slate-600 dark:text-slate-300">
                                    <span class="font-medium text-slate-900 dark:text-white">{{ $who($activity) }}</span>

                                    @switch($activity->type)
                                        @case(EnquiryActivity::NOTE)
                                            added a note

                                            @break
                                        @case(EnquiryActivity::STATUS)
                                            changed the status
                                            @if ($meta['from'] ?? null)
                                                from
                                                <x-admin.status-badge :status="$meta['from']" :dot="false" class="mx-0.5 align-middle" />
                                            @endif

                                            to
                                            <x-admin.status-badge :status="$meta['to'] ?? null" :dot="false" class="mx-0.5 align-middle" />

                                            @break
                                        @case(EnquiryActivity::REPLY)
                                            emailed
                                            <span class="font-medium text-slate-900 dark:text-white">{{ $meta['to'] ?? 'the customer' }}</span>

                                            @break
                                        @case(EnquiryActivity::ASSIGNED)
                                            @if ($meta['to'] ?? null)
                                                assigned it to
                                                <span class="font-medium text-slate-900 dark:text-white">{{ $meta['to'] }}</span>
                                            @else
                                                removed {{ $meta['from'] ?? 'the assignee' }} from it
                                            @endif

                                            @break
                                        @case(EnquiryActivity::FOLLOW_UP)
                                            @if ($meta['to'] ?? null)
                                                set a follow-up for
                                                <span class="font-medium text-slate-900 dark:text-white">
                                                    {{ local_day($meta['to']) }}
                                                </span>
                                            @else
                                                removed the follow-up
                                            @endif

                                            @break
                                        @case(EnquiryActivity::CREATED)
                                            added this enquiry
                                            @if ($meta['source'] ?? null)
                                                ({{ $meta['source'] }})
                                            @endif

                                            @break
                                        @case(EnquiryActivity::EDITED)
                                            edited {{ Str::lower(implode(', ', $meta['fields'] ?? ['the details'])) }}

                                            @break
                                        @default
                                            {{ Str::headline($activity->type) }}
                                    @endswitch
                                </p>
                                <time
                                    datetime="{{ $activity->created_at->toIso8601String() }}"
                                    title="{{ local_datetime($activity->created_at) }}"
                                    class="shrink-0 text-xs text-slate-400"
                                >
                                    {{ $activity->created_at->diffForHumans() }}
                                </time>
                            </div>

                            @if ($activity->type === EnquiryActivity::REPLY)
                                <details
                                    class="group mt-2 rounded-lg border border-slate-200 bg-slate-50/60 dark:border-slate-800 dark:bg-slate-800/30"
                                >
                                    <summary class="flex cursor-pointer list-none items-center justify-between gap-2 px-3 py-2 text-sm">
                                        <span class="truncate font-medium text-slate-800 dark:text-slate-100">
                                            {{ $meta['subject'] ?? 'Reply' }}
                                        </span>
                                        <x-admin.icon name="chevron-down" class="h-4 w-4 shrink-0 text-slate-400 transition group-open:rotate-180" />
                                    </summary>
                                    <div
                                        class="border-t border-slate-200 px-3 py-2.5 text-sm whitespace-pre-line text-slate-700 dark:border-slate-800 dark:text-slate-200"
                                    >
                                        {{ $activity->body }}
                                    </div>
                                </details>
                                @if (($meta['delivered'] ?? true) === false)
                                    <p class="mt-1 text-xs text-amber-600 dark:text-amber-400">
                                        Only written to the log: email wasn't set up when this was sent.
                                    </p>
                                @endif
                            @elseif ($activity->body)
                                <div
                                    @class([
                                        'mt-2 rounded-lg px-3 py-2.5 text-sm whitespace-pre-line',
                                        'border border-amber-200/70 bg-amber-50/60 text-slate-800 dark:border-amber-500/20 dark:bg-amber-500/5 dark:text-slate-100' =>
                                            $activity->type === EnquiryActivity::NOTE,
                                        'bg-slate-50 text-slate-700 dark:bg-slate-800/50 dark:text-slate-200' => $activity->type !== EnquiryActivity::NOTE,
                                    ])
                                >
                                    {{ $activity->body }}
                                </div>
                            @endif

                            @if ($activity->type === EnquiryActivity::NOTE && ($activity->user_id === $user->id || $canDelete))
                                <form
                                    method="POST"
                                    action="{{ route('admin.enquiries.notes.destroy', [$enquiry, $activity]) }}"
                                    class="mt-1"
                                    x-data
                                    x-on:submit="if (! confirm('Delete this note?')) $event.preventDefault()"
                                >
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="cursor-pointer text-xs text-slate-400 hover:text-red-600 dark:hover:text-red-400">
                                        Delete note
                                    </button>
                                </form>
                            @endif
                        </div>
                    </li>
                @endforeach

                @unless ($enquiry->is_manual)
                    <li class="relative flex gap-3">
                        <span
                            class="relative z-10 flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-blue-50 text-blue-600 ring-4 ring-white dark:bg-blue-500/10 dark:text-blue-300 dark:ring-slate-900"
                        >
                            <x-admin.icon name="globe" class="h-3.5 w-3.5" />
                        </span>
                        <div class="flex min-w-0 flex-1 flex-wrap items-baseline justify-between gap-x-3 pt-1">
                            <p class="text-sm text-slate-600 dark:text-slate-300">
                                Received from the
                                <span class="font-medium text-slate-900 dark:text-white">{{ $enquiry->source_label }}</span>
                            </p>
                            <time class="text-xs text-slate-400" title="{{ local_datetime($enquiry->created_at) }}">
                                {{ $enquiry->created_at->diffForHumans() }}
                            </time>
                        </div>
                    </li>
                @endunless
            </ol>
        </x-admin.card>

        <x-slot:aside>
            <x-admin.card title="Status" icon="circle-dot">
                <div class="mb-4 flex items-center justify-between gap-3">
                    <x-admin.status-badge :status="$enquiry->status" />
                    @if ($enquiry->status_changed_at)
                        <span class="text-xs text-slate-400" title="{{ local_datetime($enquiry->status_changed_at) }}">
                            since {{ $enquiry->status_changed_at->diffForHumans() }}
                        </span>
                    @endif
                </div>
                @if ($status)
                    <p class="mb-4 text-xs text-slate-500 dark:text-slate-400">{{ $status->description() }}.</p>
                @endif

                @if ($canEdit)
                    <form
                        method="POST"
                        action="{{ route('admin.enquiries.status', $enquiry) }}"
                        class="space-y-4 border-t border-slate-100 pt-4 dark:border-slate-800"
                        x-data="{
                            status: {{ Js::from(old('status', $enquiry->status)) }},
                            current: {{ Js::from($enquiry->status) }},
                            needsNote: {{ Js::from($needsNote) }},
                            submitting: false,
                            get noteRequired() {
                                return this.needsNote.includes(this.status)
                            },
                        }"
                        x-on:submit="submitting = true"
                    >
                        @csrf
                        <x-admin.form.select
                            name="status"
                            label="Change to"
                            :options="$statusOptions"
                            :value="old('status', $enquiry->status)"
                            x-model="status"
                        />

                        <div>
                            <label
                                for="status-note"
                                class="mb-1.5 flex items-center justify-between text-sm font-medium text-slate-700 dark:text-slate-200"
                            >
                                <span>
                                    Note
                                    <span x-show="noteRequired" class="text-red-500">*</span>
                                </span>
                                <span x-show="! noteRequired" class="text-xs font-normal text-slate-400">optional</span>
                            </label>
                            <textarea
                                id="status-note"
                                name="note"
                                rows="3"
                                x-bind:required="noteRequired"
                                x-bind:placeholder="
                                    noteRequired
                                        ? status === 'closed'
                                            ? 'How did it end? e.g. Won, sent quote, not interested'
                                            : 'Why is it on hold, and until when?'
                                        : 'Anything worth adding'
                                "
                                class="block w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 shadow-xs transition outline-none placeholder:text-slate-400 focus:border-blue-400 focus:ring-3 focus:ring-blue-500/20 dark:border-slate-700 dark:bg-slate-900 dark:text-white"
                            >
{{ old('note') }}</textarea
                            >
                            @error('note')
                                <p class="mt-1.5 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                        </div>

                        <x-admin.button full x-bind:disabled="submitting || status === current">
                            <span
                                x-text="
                                    status === current
                                        ? 'Pick a new status'
                                        : submitting
                                          ? 'Saving…'
                                          : 'Update status'
                                "
                            >
                                Update status
                            </span>
                        </x-admin.button>
                    </form>
                @endif
            </x-admin.card>

            <x-admin.card title="Handling" icon="user-check">
                <div class="space-y-5">
                    <div>
                        @if ($canEdit)
                            <form method="POST" action="{{ route('admin.enquiries.assign', $enquiry) }}" x-data x-on:change="$el.requestSubmit()">
                                @csrf
                                <x-admin.form.select
                                    name="assigned_to"
                                    label="Assigned to"
                                    :options="$assigneeOptions"
                                    :value="(string) ($enquiry->assigned_to ?? '')"
                                    icon="user"
                                />
                            </form>
                            @if ($enquiry->assigned_to !== $user->id && $assignees->contains('id', $user->id))
                                <form method="POST" action="{{ route('admin.enquiries.assign', $enquiry) }}" class="mt-1.5">
                                    @csrf
                                    <input type="hidden" name="assigned_to" value="{{ $user->id }}" />
                                    <button type="submit" class="cursor-pointer text-xs font-medium text-blue-600 hover:underline dark:text-blue-400">
                                        Assign to me
                                    </button>
                                </form>
                            @endif
                        @else
                            <p class="text-xs text-slate-500 dark:text-slate-400">Assigned to</p>
                            <p class="mt-0.5 text-sm font-medium text-slate-800 dark:text-slate-100">{{ $enquiry->assignee?->name ?? 'No one' }}</p>
                        @endif
                    </div>

                    <div class="border-t border-slate-100 pt-4 dark:border-slate-800">
                        <p class="mb-1.5 flex items-center justify-between text-sm font-medium text-slate-700 dark:text-slate-200">
                            Follow up on
                            @if ($enquiry->follow_up_state)
                                <span class="{{ $followUpTone[$enquiry->follow_up_state] }} text-xs font-semibold">
                                    {{ ['overdue' => 'Overdue', 'today' => 'Today', 'upcoming' => $enquiry->follow_up_at->diffForHumans()][$enquiry->follow_up_state] }}
                                </span>
                            @endif
                        </p>
                        @if ($canEdit)
                            <form
                                method="POST"
                                action="{{ route('admin.enquiries.follow-up', $enquiry) }}"
                                class="flex items-start gap-2"
                                x-data
                                x-on:change="$el.requestSubmit()"
                            >
                                @csrf
                                <div class="min-w-0 flex-1">
                                    <x-admin.form.date-picker
                                        id="follow-up-date"
                                        name="follow_up_at"
                                        :value="$enquiry->follow_up_at?->toDateString()"
                                        placeholder="No follow-up"
                                        aria-label="Follow up on"
                                    />
                                </div>
                            </form>
                            <p class="mt-1.5 text-xs text-slate-400">Shows under “Follow-up due” in the list on that day.</p>
                        @else
                            <p class="text-sm text-slate-800 dark:text-slate-100">{{ local_day($enquiry->follow_up_at) ?? 'Not set' }}</p>
                        @endif
                    </div>
                </div>
            </x-admin.card>

            <x-admin.card title="Contact" icon="user">
                <ul class="space-y-3 text-sm">
                    @if ($email)
                        <li class="flex items-center justify-between gap-2">
                            <a
                                href="mailto:{{ $email }}"
                                class="flex min-w-0 items-center gap-2 text-slate-700 hover:text-blue-600 dark:text-slate-200 dark:hover:text-blue-400"
                            >
                                <x-admin.icon name="mail" class="h-4 w-4 shrink-0 text-slate-400" />
                                <span class="truncate">{{ $email }}</span>
                            </a>
                            <button
                                type="button"
                                x-data="{ copied: false }"
                                x-on:click="
                                    window.copyText({{ Js::from($email) }}).then((ok) => {
                                        copied = ok
                                        setTimeout(() => (copied = false), 1500)
                                    })
                                "
                                class="shrink-0 cursor-pointer rounded-md p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-slate-800"
                                aria-label="Copy email"
                            >
                                <x-admin.icon name="copy" class="h-3.5 w-3.5" x-show="! copied" />
                                <x-admin.icon name="check" class="h-3.5 w-3.5 text-emerald-500" x-show="copied" x-cloak />
                            </button>
                        </li>
                    @endif

                    @if ($phone)
                        <li class="flex items-center justify-between gap-2">
                            <a
                                href="tel:{{ preg_replace('/[^\d+]/', '', $phone) }}"
                                class="flex min-w-0 items-center gap-2 text-slate-700 hover:text-blue-600 dark:text-slate-200 dark:hover:text-blue-400"
                            >
                                <x-admin.icon name="phone" class="h-4 w-4 shrink-0 text-slate-400" />
                                <span class="truncate">{{ $phone }}</span>
                            </a>
                            @if (strlen($whatsapp) >= 8)
                                <a
                                    href="https://wa.me/{{ $whatsapp }}"
                                    target="_blank"
                                    rel="noopener"
                                    class="shrink-0 rounded-md px-1.5 py-0.5 text-xs font-medium text-emerald-700 hover:bg-emerald-50 dark:text-emerald-400 dark:hover:bg-emerald-500/10"
                                >
                                    WhatsApp
                                </a>
                            @endif
                        </li>
                    @endif

                    @if ($company)
                        <li class="flex items-center gap-2 text-slate-700 dark:text-slate-200">
                            <x-admin.icon name="building" class="h-4 w-4 shrink-0 text-slate-400" />
                            <span class="truncate">{{ $company }}</span>
                        </li>
                    @endif

                    @if (! $email && ! $phone && ! $company)
                        <li class="text-slate-400">No contact details.</li>
                    @endif
                </ul>
            </x-admin.card>

            <x-admin.card title="Details" icon="info">
                <dl class="-my-2 divide-y divide-slate-100 dark:divide-slate-800">
                    <div class="{{ $dl }}">
                        <dt class="text-slate-500 dark:text-slate-400">Reference</dt>
                        <dd class="font-mono text-slate-800 dark:text-slate-100">{{ $enquiry->reference }}</dd>
                    </div>
                    <div class="{{ $dl }}">
                        <dt class="text-slate-500 dark:text-slate-400">Received</dt>
                        <dd class="text-right text-slate-800 dark:text-slate-100">{{ local_datetime($enquiry->created_at) }}</dd>
                    </div>
                    <div class="{{ $dl }}">
                        <dt class="text-slate-500 dark:text-slate-400">Source</dt>
                        <dd class="text-right text-slate-800 dark:text-slate-100">{{ $enquiry->source_label }}</dd>
                    </div>
                    @if ($enquiry->creator)
                        <div class="{{ $dl }}">
                            <dt class="text-slate-500 dark:text-slate-400">Added by</dt>
                            <dd class="text-right text-slate-800 dark:text-slate-100">{{ $enquiry->creator->name }}</dd>
                        </div>
                    @endif

                    @if ($enquiry->seen_at)
                        <div class="{{ $dl }}">
                            <dt class="text-slate-500 dark:text-slate-400">First opened</dt>
                            <dd class="text-right text-slate-800 dark:text-slate-100">
                                {{ local_datetime($enquiry->seen_at) }}
                                @if ($enquiry->seenBy)
                                    <span class="block text-xs text-slate-400">by {{ $enquiry->seenBy->name }}</span>
                                @endif
                            </dd>
                        </div>
                    @endif
                </dl>
            </x-admin.card>
        </x-slot>
    </x-admin.form-grid>
</x-admin>
