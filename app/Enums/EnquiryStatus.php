<?php

namespace App\Enums;

enum EnquiryStatus: string
{
    case NEW = 'new';
    case IN_PROGRESS = 'in_progress';
    case REPLIED = 'replied';
    case ON_HOLD = 'on_hold';
    case CLOSED = 'closed';
    case SPAM = 'spam';

    public function label(): string
    {
        return match ($this) {
            self::NEW => 'New',
            self::IN_PROGRESS => 'In progress',
            self::REPLIED => 'Replied',
            self::ON_HOLD => 'On hold',
            self::CLOSED => 'Closed',
            self::SPAM => 'Spam',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::NEW => 'Not handled yet',
            self::IN_PROGRESS => 'Someone is working on it',
            self::REPLIED => 'Answered, waiting for the customer',
            self::ON_HOLD => 'Paused for a reason',
            self::CLOSED => 'Finished, with an outcome',
            self::SPAM => 'Junk, not a real enquiry',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::NEW => 'info',
            self::IN_PROGRESS => 'brand',
            self::REPLIED => 'warning',
            self::ON_HOLD => 'neutral',
            self::CLOSED => 'success',
            self::SPAM => 'danger',
        };
    }

    public function dot(): string
    {
        return match ($this) {
            self::NEW => 'bg-blue-500',
            self::IN_PROGRESS => 'bg-violet-500',
            self::REPLIED => 'bg-amber-500',
            self::ON_HOLD => 'bg-slate-400',
            self::CLOSED => 'bg-emerald-500',
            self::SPAM => 'bg-red-500',
        };
    }

    public function needsNote(): bool
    {
        return in_array($this, [self::ON_HOLD, self::CLOSED], true);
    }

    public function isOpen(): bool
    {
        return ! in_array($this, [self::CLOSED, self::SPAM], true);
    }

    public static function open(): array
    {
        return array_map(fn (self $status) => $status->value, array_filter(self::cases(), fn (self $status) => $status->isOpen()));
    }

    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $status) => [$status->value => ['label' => $status->label(), 'dot' => $status->dot(), 'description' => $status->description()]])->all();
    }

    public static function labelFor(?string $value): string
    {
        return self::tryFrom((string) $value)?->label() ?? ucfirst(str_replace('_', ' ', (string) $value));
    }
}
