<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CommonStatusEnum;
use App\Enums\EnquiryStatus;
use App\Http\Controllers\Controller;
use App\Mail\EnquiryReplyMail;
use App\Models\Enquiry;
use App\Models\EnquiryActivity;
use App\Models\User;
use App\Support\MailSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Throwable;

class EnquiryController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('admin.enquiries.view');

        $query = Enquiry::with(['seenBy:id,name', 'assignee:id,name,avatar'])->latest();

        match ($request->status) {
            null, '' => null,
            'open' => $query->open(),
            default => $query->where('status', $request->status),
        };

        if ($request->filled('source')) {
            $query->where('source', $request->source);
        }

        if ($request->filled('assigned')) {
            match ($request->assigned) {
                'me' => $query->where('assigned_to', $request->user()->id),
                'none' => $query->whereNull('assigned_to'),
                default => $query->where('assigned_to', (int) $request->assigned),
            };
        }

        if ($request->filled('follow_up')) {
            match ($request->follow_up) {
                'due' => $query->followUpDue(),
                'upcoming' => $query->open()->where('follow_up_at', '>', now()->endOfDay()),
                'none' => $query->whereNull('follow_up_at'),
                default => null,
            };
        }

        if ($request->filled('seen')) {
            match ($request->seen) {
                'unseen' => $query->whereNull('seen_at'),
                'seen' => $query->whereNotNull('seen_at'),
                default => null,
            };
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        if ($request->filled('search')) {
            $term = '%'.mb_strtolower(trim($request->search)).'%';
            $query->where(function ($q) use ($term) {
                foreach (['name', 'email', 'phone', 'company', 'subject', 'message'] as $field) {
                    $q->orWhereRaw("LOWER(JSON_UNQUOTE(JSON_EXTRACT(data, '$.{$field}'))) LIKE ?", [$term]);
                }
            });
        }

        return view('admin.enquiries.index', [
            'enquiries' => $query->paginate(20)->withQueryString(),
            'sources' => cache()->remember('enquiry_sources', 300, fn () => Enquiry::distinct()->orderBy('source')->pluck('source')),
            'assignees' => $this->assignees(),
            'counts' => Enquiry::selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status'),
            'dueCount' => Enquiry::followUpDue()->count(),
            'mineCount' => Enquiry::open()->where('assigned_to', $request->user()->id)->count(),
        ]);
    }

    public function create()
    {
        Gate::authorize('admin.enquiries.create');

        return view('admin.enquiries.create', ['assignees' => $this->assignees()]);
    }

    public function store(Request $request)
    {
        Gate::authorize('admin.enquiries.create');

        $validated = $request->validate([
            ...$this->contactRules(),
            'source' => ['required', Rule::in(array_keys(Enquiry::MANUAL_SOURCES))],
            'status' => ['required', Rule::in([EnquiryStatus::NEW->value, EnquiryStatus::IN_PROGRESS->value])],
            'assigned_to' => ['nullable', Rule::in($this->assignees()->pluck('id'))],
            'follow_up_at' => ['nullable', 'date_format:Y-m-d'],
            'note' => ['nullable', 'string', 'max:5000'],
        ], $this->messages());

        $user = $request->user();

        $enquiry = Enquiry::create([
            'source' => $validated['source'],
            'data' => $this->contactData($validated),
            'status' => $validated['status'],
            'status_changed_at' => now(),
            'seen_at' => now(),
            'seen_by' => $user->id,
            'assigned_to' => $validated['assigned_to'] ?? null,
            'follow_up_at' => filled($validated['follow_up_at'] ?? null) ? Carbon::parse($validated['follow_up_at'])->startOfDay() : null,
            'created_by' => $user->id,
        ]);

        $enquiry->record(EnquiryActivity::CREATED, null, ['source' => $enquiry->source_label], $user);

        if (filled($validated['note'] ?? null)) {
            $enquiry->record(EnquiryActivity::NOTE, $validated['note'], [], $user);
        }

        cache()->forget('enquiry_sources');

        return to_route('admin.enquiries.show', $enquiry)->with('success', 'Enquiry added.');
    }

    public function show(Request $request, Enquiry $enquiry)
    {
        Gate::authorize('admin.enquiries.view');

        $enquiry->markSeen($request->user());
        $enquiry->load(['seenBy:id,name', 'assignee:id,name,email,avatar', 'creator:id,name', 'activities.user:id,name,avatar']);

        return view('admin.enquiries.show', [
            'enquiry' => $enquiry,
            'assignees' => $this->assignees(),
            'canDeliver' => MailSettings::canDeliver(),
            'previous' => Enquiry::where('id', '>', $enquiry->id)->orderBy('id')->value('id'),
            'next' => Enquiry::where('id', '<', $enquiry->id)->orderByDesc('id')->value('id'),
        ]);
    }

    public function edit(Enquiry $enquiry)
    {
        Gate::authorize('admin.enquiries.edit');

        return view('admin.enquiries.edit', ['enquiry' => $enquiry]);
    }

    public function update(Request $request, Enquiry $enquiry)
    {
        Gate::authorize('admin.enquiries.edit');

        $rules = $this->contactRules();
        if ($enquiry->is_manual) {
            $rules['source'] = ['required', Rule::in(array_keys(Enquiry::MANUAL_SOURCES))];
        }
        $validated = $request->validate($rules, $this->messages());

        $before = $enquiry->data ?? [];
        $after = array_filter([...$before, ...$this->contactData($validated, keepEmpty: true)], fn ($value) => $value !== null && $value !== '');
        $changed = collect(Enquiry::CONTACT_FIELDS)->filter(fn ($field) => ($before[$field] ?? null) !== ($after[$field] ?? null))->values();

        $sourceChanged = isset($validated['source']) && $validated['source'] !== $enquiry->source;
        if ($changed->isEmpty() && ! $sourceChanged) {
            return to_route('admin.enquiries.show', $enquiry)->with('success', 'Nothing changed.');
        }

        $enquiry->update(['data' => $after, 'source' => $sourceChanged ? $validated['source'] : $enquiry->source]);

        $labels = $changed->map(fn ($field) => Str::headline($field));
        if ($sourceChanged) {
            $labels->push('source');
        }
        $enquiry->record(EnquiryActivity::EDITED, null, ['fields' => $labels->all()], $request->user());

        return to_route('admin.enquiries.show', $enquiry)->with('success', 'Enquiry details saved.');
    }

    public function status(Request $request, Enquiry $enquiry)
    {
        Gate::authorize('admin.enquiries.edit');

        $status = EnquiryStatus::tryFrom((string) $request->input('status'));

        $request->validate([
            'status' => ['required', Rule::enum(EnquiryStatus::class)],
            'note' => [Rule::requiredIf($status?->needsNote() ?? false), 'nullable', 'string', 'max:5000'],
        ], [
            'note.required' => 'Add a note saying why, so the team knows what happened.',
        ]);

        if (! $enquiry->changeStatus($status, $request->input('note'), $request->user())) {
            if ($request->filled('note')) {
                $enquiry->record(EnquiryActivity::NOTE, $request->input('note'), [], $request->user());

                return $this->backToActivity($enquiry, 'Note added.');
            }

            return $this->backToActivity($enquiry, 'It is already '.$status->label().'.');
        }

        return $this->backToActivity($enquiry, 'Status changed to '.$status->label().'.');
    }

    public function note(Request $request, Enquiry $enquiry)
    {
        Gate::authorize('admin.enquiries.edit');

        $validated = $request->validate(['body' => ['required', 'string', 'max:5000']], ['body.required' => 'Write the note first.']);

        $enquiry->record(EnquiryActivity::NOTE, $validated['body'], [], $request->user());

        if ($enquiry->status === EnquiryStatus::NEW->value) {
            $enquiry->changeStatus(EnquiryStatus::IN_PROGRESS, null, $request->user());
        }

        return $this->backToActivity($enquiry, 'Note added.');
    }

    public function destroyNote(Request $request, Enquiry $enquiry, EnquiryActivity $activity)
    {
        abort_unless($activity->enquiry_id === $enquiry->id && $activity->type === EnquiryActivity::NOTE, 404);
        abort_unless($activity->user_id === $request->user()->id || $request->user()->can('admin.enquiries.delete'), 403);

        $activity->delete();

        return $this->backToActivity($enquiry, 'Note deleted.');
    }

    public function assign(Request $request, Enquiry $enquiry)
    {
        Gate::authorize('admin.enquiries.edit');

        $validated = $request->validate(['assigned_to' => ['nullable', Rule::in($this->assignees()->pluck('id'))]]);
        $assignee = filled($validated['assigned_to'] ?? null) ? User::find($validated['assigned_to']) : null;

        $enquiry->assignTo($assignee, $request->user());

        return $this->backToActivity($enquiry, $assignee ? "Assigned to {$assignee->name}." : 'No one is assigned now.');
    }

    public function followUp(Request $request, Enquiry $enquiry)
    {
        Gate::authorize('admin.enquiries.edit');

        $validated = $request->validate([
            'follow_up_at' => ['nullable', 'date_format:Y-m-d'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);
        $when = filled($validated['follow_up_at'] ?? null) ? Carbon::parse($validated['follow_up_at']) : null;

        $enquiry->scheduleFollowUp($when, $validated['note'] ?? null, $request->user());

        return $this->backToActivity($enquiry, $when ? 'Follow-up set for '.$when->format('d M Y').'.' : 'Follow-up removed.');
    }

    public function reply(Request $request, Enquiry $enquiry)
    {
        Gate::authorize('admin.enquiries.reply');

        $to = $enquiry->field('email');
        abort_unless($to && filter_var($to, FILTER_VALIDATE_EMAIL), 422, 'This enquiry has no valid email address.');

        $validated = $request->validateWithBag('reply', [
            'subject' => ['required', 'string', 'max:200'],
            'body' => ['required', 'string', 'max:10000'],
            'quote' => ['boolean'],
        ], ['body.required' => 'Write your reply first.']);

        $user = $request->user();

        try {
            Mail::to($to, $enquiry->field('name'))->send(new EnquiryReplyMail($enquiry, $user, $validated['subject'], $validated['body'], $request->boolean('quote')));
        } catch (Throwable $e) {
            report($e);

            return back()->withInput()->withErrors([
                'body' => 'The email could not be sent. '.MailSettings::explain($e->getMessage(), (array) config('mail.mailers.'.config('mail.default'))),
            ], 'reply');
        }

        $enquiry->record(EnquiryActivity::REPLY, $validated['body'], ['subject' => $validated['subject'], 'to' => $to, 'delivered' => MailSettings::canDeliver()], $user);

        if (in_array($enquiry->status, [EnquiryStatus::NEW->value, EnquiryStatus::IN_PROGRESS->value, EnquiryStatus::ON_HOLD->value], true)) {
            $enquiry->changeStatus(EnquiryStatus::REPLIED, null, $user);
        }

        return $this->backToActivity($enquiry, "Reply sent to {$to}.");
    }

    public function destroy(Enquiry $enquiry)
    {
        Gate::authorize('admin.enquiries.delete');

        $enquiry->delete();

        return to_route('admin.enquiries.index')->with('success', 'Enquiry moved to the trash.');
    }

    private function assignees(): Collection
    {
        return User::where('status', CommonStatusEnum::ACTIVE->value)->orderBy('name')->get(['id', 'name', 'email', 'avatar'])
            ->filter(fn (User $user) => $user->can('admin.enquiries.view'))
            ->values();
    }

    private function contactRules(): array
    {
        return [
            'name' => ['nullable', 'string', 'max:120'],
            'email' => ['nullable', 'required_without:phone', 'email:rfc', 'max:255'],
            'phone' => ['nullable', 'required_without:email', 'string', 'max:40', 'regex:/^[0-9+()\-.\s]{5,40}$/'],
            'company' => ['nullable', 'string', 'max:150'],
            'subject' => ['nullable', 'string', 'max:200'],
            'message' => ['required', 'string', 'max:10000'],
        ];
    }

    private function messages(): array
    {
        return [
            'email.required_without' => 'Add an email address or a phone number, so you can get back to them.',
            'phone.required_without' => 'Add a phone number or an email address, so you can get back to them.',
            'phone.regex' => 'Use digits, spaces and + ( ) - only.',
            'message.required' => 'Write what they asked about.',
        ];
    }

    private function contactData(array $validated, bool $keepEmpty = false): array
    {
        $data = [];
        foreach (Enquiry::CONTACT_FIELDS as $field) {
            $value = isset($validated[$field]) ? trim((string) $validated[$field]) : '';
            if ($value !== '' || $keepEmpty) {
                $data[$field] = $value;
            }
        }

        return $data;
    }

    private function backToActivity(Enquiry $enquiry, string $message)
    {
        return redirect()->to(route('admin.enquiries.show', $enquiry).'#activity')->with('success', $message);
    }
}
