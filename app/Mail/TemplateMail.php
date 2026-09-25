<?php

namespace App\Mail;

use App\Support\EmailTemplates;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

abstract class TemplateMail extends Mailable
{
    private ?array $composed = null;

    abstract protected function templateKey(): string;

    abstract protected function variables(): array;

    protected function details(): array
    {
        return [];
    }

    protected function actionUrl(): ?string
    {
        return null;
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->composed()['subject']);
    }

    public function content(): Content
    {
        $composed = $this->composed();

        return new Content(view: $composed['view'], text: $composed['text'], with: $composed['data']);
    }

    protected function composed(): array
    {
        return $this->composed ??= EmailTemplates::compose($this->templateKey(), $this->variables(), $this->details(), $this->actionUrl());
    }
}
