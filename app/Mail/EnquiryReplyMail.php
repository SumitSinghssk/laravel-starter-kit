<?php

namespace App\Mail;

use App\Helpers\Settings;
use App\Models\Enquiry;
use App\Models\User;
use App\Support\EmailTemplates;
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

    public static function variablesFor(Enquiry $enquiry, User $sender): array
    {
        return [
            'name' => (string) $enquiry->field('name'),
            'email' => (string) $enquiry->field('email'),
            'enquiry_subject' => filled($enquiry->field('subject')) ? (string) $enquiry->field('subject') : 'Your enquiry to '.Settings::appName(),
            'reference' => $enquiry->reference,
            'sender_name' => $sender->name,
        ];
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->subjectLine,
            replyTo: [new Address($this->sender->email, $this->sender->name)],
        );
    }

    public function content(): Content
    {
        $composed = EmailTemplates::composeReply(
            self::variablesFor($this->enquiry, $this->sender),
            $this->subjectLine,
            $this->body,
            $this->quoteOriginal ? $this->enquiry->field('message') : null,
            LocalTime::date($this->enquiry->created_at),
        );

        return new Content(view: $composed['view'], text: $composed['text'], with: $composed['data']);
    }
}
