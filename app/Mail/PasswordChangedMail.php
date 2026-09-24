<?php

namespace App\Mail;

use App\Helpers\Settings;
use App\Models\User;
use App\Support\UserAgent;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class PasswordChangedMail extends Mailable
{
    public function __construct(public User $user, public ?string $ip = null, public ?string $userAgent = null) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Your '.Settings::appName().' password was changed');
    }

    public function content(): Content
    {
        $agent = UserAgent::describe($this->userAgent);

        return new Content(
            view: 'emails.password-changed',
            with: [
                'appName' => Settings::appName(),
                'name' => $this->user->name,
                'email' => $this->user->email,
                'when' => now()->format('d M Y, H:i').' ('.config('app.timezone').')',
                'device' => $this->userAgent ? $agent['browser'].' on '.$agent['os'] : 'Server command',
                'ip' => $this->ip ?: 'Unknown',
            ],
        );
    }
}
