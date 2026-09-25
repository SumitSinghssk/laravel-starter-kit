<?php

namespace App\Mail;

use App\Helpers\Settings;
use App\Models\User;
use App\Support\LocalTime;
use App\Support\UserAgent;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class TwoFactorNoticeMail extends Mailable
{
    public const EVENTS = [
        'enabled' => ['Two-factor sign-in is on', 'Two-factor sign-in was turned on for your account. From now on you need a code from your authenticator app when you sign in.', false],
        'changed' => ['Two-factor sign-in moved to a new app', 'Two-factor sign-in was set up again with a new authenticator app. Codes from the old app no longer work.', true],
        'disabled' => ['Two-factor sign-in was turned off', 'Two-factor sign-in was turned off for your account. Only your password is needed to sign in now.', true],
        'reset' => ['Your two-factor sign-in was reset', 'An administrator reset two-factor sign-in on your account. You can set it up again with your authenticator app.', true],
        'recovery' => ['A recovery code was used', 'Someone signed in to your account with one of your recovery codes instead of a code from your app. Each recovery code works only once.', true],
    ];

    public function __construct(
        public User $user,
        public string $event,
        public ?string $ip = null,
        public ?string $userAgent = null,
        public ?User $by = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: self::EVENTS[$this->event][0].' · '.Settings::appName());
    }

    public function content(): Content
    {
        [$title, $text, $warn] = self::EVENTS[$this->event];
        $agent = UserAgent::describe($this->userAgent);

        return new Content(
            view: 'emails.two-factor-notice',
            with: [
                'appName' => Settings::appName(),
                'title' => $title,
                'text' => $text,
                'warn' => $warn,
                'name' => $this->user->name,
                'email' => $this->user->email,
                'when' => LocalTime::dateTime(now(), true),
                'device' => $this->userAgent ? $agent['browser'].' on '.$agent['os'] : 'Server command',
                'ip' => $this->ip ?: 'Unknown',
                'by' => $this->by?->name,
            ],
        );
    }
}
