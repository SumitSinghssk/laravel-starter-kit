<?php

namespace App\Models;

use App\Enums\EnquiryStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class Enquiry extends Model
{
    use HasFactory, SoftDeletes;

    public const CONTACT_FIELDS = ['name', 'email', 'phone', 'company', 'subject', 'message'];

    public const MANUAL_SOURCES = [
        'phone-call' => 'Phone call',
        'walk-in' => 'Walk-in',
        'email' => 'Email',
        'whatsapp' => 'WhatsApp',
        'social-media' => 'Social media',
        'referral' => 'Referral',
        'event' => 'Event or exhibition',
        'other' => 'Other',
    ];

    protected $fillable = [
        'source',
        'source_url',
        'data',
        'status',
        'status_changed_at',
        'seen_at',
        'seen_by',
        'assigned_to',
        'follow_up_at',
        'created_by',
    ];

    protected $casts = [
        'data' => 'array',
        'seen_at' => 'datetime',
        'status_changed_at' => 'datetime',
        'follow_up_at' => 'datetime',
    ];

    public function seenBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seen_by');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to')->withTrashed();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    public function activities(): HasMany
    {
        return $this->hasMany(EnquiryActivity::class)->latest()->latest('id');
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', EnquiryStatus::open());
    }

    public function scopeFollowUpDue(Builder $query): Builder
    {
        return $query->open()->whereNotNull('follow_up_at')->where('follow_up_at', '<=', now()->endOfDay());
    }

    public function field(string $key): ?string
    {
        $value = ($this->data ?? [])[$key] ?? null;

        return is_scalar($value) && trim((string) $value) !== '' ? trim((string) $value) : null;
    }

    public function getDisplayNameAttribute(): string
    {
        return $this->field('name') ?? $this->field('email') ?? $this->field('phone') ?? 'Anonymous';
    }

    public function getInitialsAttribute(): ?string
    {
        $name = $this->field('name');

        return $name
            ? collect(preg_split('/\s+/', $name))->filter()->take(2)->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))->implode('')
            : null;
    }

    public function getReferenceAttribute(): string
    {
        return 'ENQ-'.str_pad((string) $this->id, 5, '0', STR_PAD_LEFT);
    }

    public function getStatusEnumAttribute(): ?EnquiryStatus
    {
        return EnquiryStatus::tryFrom((string) $this->status);
    }

    public function getSourceLabelAttribute(): string
    {
        return self::MANUAL_SOURCES[$this->source] ?? Str::headline((string) $this->source);
    }

    public function getIsManualAttribute(): bool
    {
        return $this->created_by !== null || array_key_exists((string) $this->source, self::MANUAL_SOURCES);
    }

    public function getIsUnseenAttribute(): bool
    {
        return is_null($this->seen_at);
    }

    public function getFollowUpStateAttribute(): ?string
    {
        if (! $this->follow_up_at || ! $this->status_enum?->isOpen()) {
            return null;
        }

        return match (true) {
            $this->follow_up_at->isBefore(today()) => 'overdue',
            $this->follow_up_at->isToday() => 'today',
            default => 'upcoming',
        };
    }

    public function getStatusColorAttribute(): string
    {
        return $this->status_enum?->tone() ?? 'neutral';
    }

    public function record(string $type, ?string $body = null, array $meta = [], ?User $user = null): EnquiryActivity
    {
        return $this->activities()->create([
            'user_id' => $user?->id ?? auth()->id(),
            'type' => $type,
            'body' => filled($body) ? trim($body) : null,
            'meta' => $meta ?: null,
        ]);
    }

    public function markSeen(?User $user): void
    {
        if ($this->seen_at === null) {
            $this->forceFill(['seen_at' => now(), 'seen_by' => $user?->id])->saveQuietly();
        }
    }

    public function changeStatus(EnquiryStatus $to, ?string $note = null, ?User $user = null): bool
    {
        $from = $this->status;

        if ($from === $to->value) {
            return false;
        }

        $this->forceFill([
            'status' => $to->value,
            'status_changed_at' => now(),
            'seen_at' => $to === EnquiryStatus::NEW ? $this->seen_at : ($this->seen_at ?? now()),
            'seen_by' => $to === EnquiryStatus::NEW ? $this->seen_by : ($this->seen_by ?? $user?->id ?? auth()->id()),
        ])->save();

        $this->record(EnquiryActivity::STATUS, $note, ['from' => $from, 'to' => $to->value], $user);

        return true;
    }

    public function assignTo(?User $assignee, ?User $user = null): bool
    {
        if ((int) $this->assigned_to === (int) $assignee?->id) {
            return false;
        }

        $previous = $this->assignee?->name;
        $this->forceFill(['assigned_to' => $assignee?->id])->save();
        $this->setRelation('assignee', $assignee);

        $this->record(EnquiryActivity::ASSIGNED, null, ['from' => $previous, 'to' => $assignee?->name], $user);

        return true;
    }

    public function scheduleFollowUp(?Carbon $when, ?string $note = null, ?User $user = null): bool
    {
        $current = $this->follow_up_at?->toDateString();

        if ($current === $when?->toDateString() && blank($note)) {
            return false;
        }

        $this->forceFill(['follow_up_at' => $when?->copy()->startOfDay()])->save();

        $this->record(EnquiryActivity::FOLLOW_UP, $note, ['from' => $current, 'to' => $when?->toDateString()], $user);

        return true;
    }
}
