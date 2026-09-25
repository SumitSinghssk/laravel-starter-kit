<?php

namespace App\Mail;

use App\Models\User;
use App\Support\LocalTime;
use App\Support\UserAgent;

class PasswordChangedMail extends TemplateMail
{
    public function __construct(public User $user, public ?string $ip = null, public ?string $userAgent = null) {}

    protected function templateKey(): string
    {
        return 'password_changed';
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
        ];
    }

    protected function details(): array
    {
        $variables = $this->variables();

        return ['When' => $variables['when'], 'Device' => $variables['device'], 'IP address' => $variables['ip']];
    }
}
