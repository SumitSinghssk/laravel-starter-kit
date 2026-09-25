<?php

namespace App\Mail;

use App\Models\User;
use App\Support\UserAgent;

class AdminPasswordResetMail extends TemplateMail
{
    public function __construct(public User $user, public string $token, public ?string $ip = null, public ?string $userAgent = null) {}

    protected function templateKey(): string
    {
        return 'password_reset';
    }

    protected function variables(): array
    {
        $agent = UserAgent::describe($this->userAgent);

        return [
            'name' => $this->user->name,
            'email' => $this->user->email,
            'minutes' => (int) config('auth.passwords.users.expire', 60),
            'device' => $agent['browser'].' on '.$agent['os'],
            'ip' => $this->ip ?: 'Unknown',
        ];
    }

    protected function details(): array
    {
        $variables = $this->variables();

        return ['Requested from' => $variables['device'], 'IP address' => $variables['ip']];
    }

    protected function actionUrl(): ?string
    {
        return route('admin.password.reset', ['token' => $this->token, 'email' => $this->user->email]);
    }
}
