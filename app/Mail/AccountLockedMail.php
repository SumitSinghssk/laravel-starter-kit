<?php

namespace App\Mail;

use App\Models\User;
use App\Support\LocalTime;
use App\Support\UserAgent;

class AccountLockedMail extends TemplateMail
{
    public function __construct(public User $user, public int $failures, public ?string $ip = null, public ?string $userAgent = null) {}

    protected function templateKey(): string
    {
        return 'account_locked';
    }

    protected function variables(): array
    {
        $agent = UserAgent::describe($this->userAgent);

        return [
            'name' => $this->user->name,
            'email' => $this->user->email,
            'failures' => $this->failures,
            'unlock_info' => $this->user->locked_until
                ? 'It unlocks by itself at '.LocalTime::dateTime($this->user->locked_until, true).'.'
                : 'An administrator needs to unlock it.',
            'when' => LocalTime::dateTime(now(), true),
            'device' => $agent['browser'].' on '.$agent['os'],
            'ip' => $this->ip ?: 'Unknown',
        ];
    }

    protected function details(): array
    {
        $variables = $this->variables();

        return ['When' => $variables['when'], 'Last attempt from' => $variables['device'], 'IP address' => $variables['ip']];
    }

    protected function actionUrl(): ?string
    {
        return route('admin.password.request');
    }
}
