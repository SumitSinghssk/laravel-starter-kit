<?php

namespace App\Mail;

use App\Models\User;
use App\Support\LocalTime;
use App\Support\UserAgent;

class TwoFactorNoticeMail extends TemplateMail
{
    public const EVENTS = ['enabled', 'changed', 'disabled', 'reset', 'recovery'];

    public function __construct(
        public User $user,
        public string $event,
        public ?string $ip = null,
        public ?string $userAgent = null,
        public ?User $by = null,
    ) {}

    public static function templateFor(string $event): string
    {
        return 'two_factor_'.$event;
    }

    protected function templateKey(): string
    {
        return self::templateFor($this->event);
    }

    protected function variables(): array
    {
        $agent = UserAgent::describe($this->userAgent);

        return [
            'name' => $this->user->name,
            'email' => $this->user->email,
            'when' => LocalTime::dateTime(now(), true),
            'device' => $this->userAgent ? $agent['browser'].' on '.$agent['os'] : 'Server command',
            'ip' => $this->ip ?: 'Unknown',
            'done_by' => $this->by?->name ?? '',
        ];
    }

    protected function details(): array
    {
        $variables = $this->variables();

        return [
            'Account' => $variables['email'],
            'When' => $variables['when'],
            'Done by' => $variables['done_by'],
            'Device' => $variables['device'],
            'IP address' => $variables['ip'],
        ];
    }
}
