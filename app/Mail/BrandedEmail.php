<?php

namespace App\Mail;

use App\Models\Setting;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

// Branded Areterra email: logo header, address/charity-number footer,
// From "Areterra Team", Reply-To from Hub Settings (SPEC.md §5).
class BrandedEmail extends Mailable
{
    public function __construct(
        public string $emailSubject,
        public string $bodyText,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(config('mail.from.address'), 'Areterra Team'),
            replyTo: [new Address(Setting::get('reply_to', 'team@areterra.co.uk'))],
            subject: $this->emailSubject,
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.branded', with: ['bodyText' => $this->bodyText]);
    }
}
