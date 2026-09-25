<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class TemplateTestMail extends Mailable
{
    public function __construct(public array $composed) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: '[Test] '.$this->composed['subject']);
    }

    public function content(): Content
    {
        return new Content(view: $this->composed['view'], text: $this->composed['text'], with: $this->composed['data']);
    }
}
