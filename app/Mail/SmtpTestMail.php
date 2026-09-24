<?php

namespace App\Mail;

use App\Helpers\Settings;
use App\Support\MailSettings;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class SmtpTestMail extends Mailable
{
    public function __construct(public array $values) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Test email from '.Settings::appName());
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.smtp-test',
            with: [
                'appName' => Settings::appName(),
                'server' => $this->values['host'].':'.$this->values['port'],
                'encryption' => MailSettings::ENCRYPTIONS[$this->values['encryption'] ?? 'tls'] ?? 'TLS',
                'fromAddress' => $this->values['from_address'],
                'sentAt' => now()->format('d M Y, H:i'),
            ],
        );
    }
}
