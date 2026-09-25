<?php

namespace App\Mail;

use App\Helpers\Settings;
use App\Models\Enquiry;
use App\Models\User;
use App\Support\LocalTime;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class EnquiryReplyMail extends Mailable
{
    public function __construct(
        public Enquiry $enquiry,
        public User $sender,
        public string $subjectLine,
        public string $body,
        public bool $quoteOriginal = true,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->subjectLine,
            replyTo: [new Address($this->sender->email, $this->sender->name)],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.enquiry-reply',
            text: 'emails.enquiry-reply-text',
            with: [
                'appName' => Settings::appName(),
                'body' => $this->body,
                'senderName' => $this->sender->name,
                'original' => $this->quoteOriginal ? $this->enquiry->field('message') : null,
                'receivedAt' => LocalTime::date($this->enquiry->created_at),
                'reference' => $this->enquiry->reference,
            ],
        );
    }
}
