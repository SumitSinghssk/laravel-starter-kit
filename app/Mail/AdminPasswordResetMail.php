<?php

namespace App\Mail;

use App\Helpers\Settings;
use App\Models\User;
use App\Support\UserAgent;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class AdminPasswordResetMail extends Mailable
{
    public function __construct(public User $user, public string $token, public ?string $ip = null, public ?string $userAgent = null) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Reset your '.Settings::appName().' password');
    }

    public function content(): Content
    {
        $agent = UserAgent::describe($this->userAgent);

        return new Content(
            view: 'emails.admin-password-reset',
            with: [
                'appName' => Settings::appName(),
                'name' => $this->user->name,
                'email' => $this->user->email,
                'url' => route('admin.password.reset', ['token' => $this->token, 'email' => $this->user->email]),
                'minutes' => (int) config('auth.passwords.users.expire', 60),
                'device' => $agent['browser'].' on '.$agent['os'],
                'ip' => $this->ip ?: 'Unknown',
            ],
        );
    }
}
