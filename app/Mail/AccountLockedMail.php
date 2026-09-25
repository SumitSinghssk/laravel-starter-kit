<?php

namespace App\Mail;

use App\Helpers\Settings;
use App\Models\User;
use App\Support\LocalTime;
use App\Support\UserAgent;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class AccountLockedMail extends Mailable
{
    public function __construct(public User $user, public int $failures, public ?string $ip = null, public ?string $userAgent = null) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Your '.Settings::appName().' account was locked');
    }

    public function content(): Content
    {
        $agent = UserAgent::describe($this->userAgent);

        return new Content(
            view: 'emails.account-locked',
            with: [
                'appName' => Settings::appName(),
                'name' => $this->user->name,
                'email' => $this->user->email,
                'failures' => $this->failures,
                'until' => $this->user->locked_until ? LocalTime::dateTime($this->user->locked_until, true) : null,
                'when' => LocalTime::dateTime(now(), true),
                'device' => $agent['browser'].' on '.$agent['os'],
                'ip' => $this->ip ?: 'Unknown',
                'resetUrl' => route('admin.password.request'),
            ],
        );
    }
}
